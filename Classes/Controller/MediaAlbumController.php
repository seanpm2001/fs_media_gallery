<?php
namespace MiniFranske\FsMediaGallery\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Frans Saris <franssaris@gmail.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use MiniFranske\FsMediaGallery\Domain\Model\MediaAlbum;
use MiniFranske\FsMediaGallery\Utility\PageUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * MediaAlbumController
 */
class MediaAlbumController extends ActionController {

	/**
	 * Injects the Configuration Manager
	 *
	 * @param ConfigurationManagerInterface $configurationManager Instance of the Configuration Manager
	 * @return void
	 */
	public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager) {
		$this->configurationManager = $configurationManager;

		$frameworkSettings = $this->configurationManager->getConfiguration(
			ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
			'fsmediagallery',
			'fsmediagallery_mediagallery'
		);
		$flexformSettings = $this->configurationManager->getConfiguration(
			ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
		);

		// merge Framework (TypoScript) and Flexform settings
		if (isset($frameworkSettings['settings']['overrideFlexformSettingsIfEmpty'])) {
			/** @var $typoScriptUtility \MiniFranske\FsMediaGallery\Utility\TypoScriptUtility */
			$typoScriptUtility = GeneralUtility::makeInstance('MiniFranske\\FsMediaGallery\\Utility\\TypoScriptUtility');
			$mergedSettings = $typoScriptUtility->override($flexformSettings, $frameworkSettings);
			$this->settings = $mergedSettings;
		} else {
			$this->settings = $flexformSettings;
		}

		/**
		 * sync persistence.storagePid=settings.startingpoint and persistence.recursive=settings.recursive
		 */
		// overwrite persistence.storagePid if settings.startingpoint is defined in flexform
		if (!empty($this->settings['startingpoint'])) {
			$frameworkSettings['persistence']['storagePid'] = $this->settings['startingpoint'];
		} else {
			// if settings.startingpoint is not set in flexform, use persistence.storagePid from TS
			if (!empty($frameworkSettings['persistence']['storagePid'])) {
				$this->settings['startingpoint'] = $frameworkSettings['persistence']['storagePid'];
			}
		}
		if (empty($this->settings['startingpoint'])) {
			// startingpoint/storagePid is not set via TS nor flexforms > fallback to current pid
			$this->settings['startingpoint'] = $frameworkSettings['persistence']['storagePid'] = $GLOBALS['TSFE']->id;
		}
		// set persistence.recursive if settings.recursive is defined in flexform
		if (!empty($this->settings['recursive'])) {
			$frameworkSettings['persistence']['recursive'] = $this->settings['recursive'];
		} else {
			// if settings.recursive is not set in flexform, use persistence.recursive from TS
			if (!empty($frameworkSettings['persistence']['recursive'])) {
				$this->settings['recursive'] = $frameworkSettings['persistence']['recursive'];
			}
		}
		if (empty($this->settings['recursive'])) {
			// recursive is not set via TS nor flexforms
			$this->settings['recursive'] = $frameworkSettings['persistence']['recursive'] = 0;
		}
		// write back altered configuration
		$this->configurationManager->setConfiguration($frameworkSettings);

		// check some settings
		if (!isset($this->settings['list']['pagination']['itemsPerPage']) || $this->settings['list']['pagination']['itemsPerPage'] < 1) {
			$this->settings['list']['pagination']['itemsPerPage'] = 12;
		}
		if (!isset($this->settings['album']['pagination']['itemsPerPage']) || $this->settings['album']['pagination']['itemsPerPage'] < 1) {
			$this->settings['album']['pagination']['itemsPerPage'] = 12;
		}
		// correct resizeMode 's' set in flexforms (flexforms value '' is used for inherit/definition by TS)
		if (isset($this->settings['list']['thumb']['resizeMode']) && $this->settings['list']['thumb']['resizeMode'] == 's') {
			$this->settings['list']['thumb']['resizeMode'] = '';
		}
		if (isset($this->settings['album']['thumb']['resizeMode']) && $this->settings['album']['thumb']['resizeMode'] == 's') {
			$this->settings['album']['thumb']['resizeMode'] = '';
		}
		if (isset($this->settings['detail']['asset']['resizeMode']) && $this->settings['detail']['asset']['resizeMode'] == 's') {
			$this->settings['detail']['asset']['resizeMode'] = '';
		}
		if (isset($this->settings['random']['thumb']['resizeMode']) && $this->settings['random']['thumb']['resizeMode'] == 's') {
			$this->settings['random']['thumb']['resizeMode'] = '';
		}
	}

	/**
	 * mediaAlbumRepository
	 *
	 * @var \MiniFranske\FsMediaGallery\Domain\Repository\MediaAlbumRepository
	 */
	protected $mediaAlbumRepository;

	/**
	 * Injects the MediaAlbumRepository
	 *
	 * @param \MiniFranske\FsMediaGallery\Domain\Repository\MediaAlbumRepository $mediaAlbumRepository
	 * @return void
	 */
	public function injectMediaAlbumRepository(\MiniFranske\FsMediaGallery\Domain\Repository\MediaAlbumRepository $mediaAlbumRepository) {
		$this->mediaAlbumRepository = $mediaAlbumRepository;
		if (!empty($this->settings['allowedAssetMimeTypes'])) {
			$this->mediaAlbumRepository->setAllowedAssetMimeTypes(GeneralUtility::trimExplode(',', $this->settings['allowedAssetMimeTypes']));
		}
	}

	/**
	 * Index Action
	 *
	 * As switchableControllerActions can be limited in EM this function
	 * is needed as default action (with no output).
	 * It is set as default action in flexform to make sure the
	 * correct tabs/fields are shown when a new plugin is added.
	 *
	 * @return string
	 */
	public function indexAction() {
		return '';
	}

	/**
	 * NestedList Action
	 * Displays a (nested) list of albums; default/show action in fs_media_gallery <= 1.0.0
	 *
	 * @param int $mediaAlbum (this is not directly mapped to an object to handle 404 on our own)
	 * @return void
	 */
	public function nestedListAction($mediaAlbum = 0) {
		$mediaAlbums = NULL;
		$mediaAlbum = (int)$mediaAlbum ?: NULL;
		$mediaAlbumsUids = array();
		$useAlbumFilterAsExclude = !empty($this->settings['useAlbumFilterAsExclude']);
		$showBackLink = TRUE;

		if (!empty($this->settings['mediaAlbums'])) {
			$mediaAlbumsUids = GeneralUtility::trimExplode(',', $this->settings['mediaAlbums']);
		}

		if ($mediaAlbum) {
			/** @var MediaAlbum $mediaAlbum */
			$mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbum);
			if ($mediaAlbum && $mediaAlbumsUids !== array() && !$useAlbumFilterAsExclude && !in_array($mediaAlbum->getUid(), $mediaAlbumsUids)) {
				$mediaAlbum = NULL;
			}
			if ($mediaAlbum && $mediaAlbumsUids !== array() && $useAlbumFilterAsExclude && in_array($mediaAlbum->getUid(), $mediaAlbumsUids)) {
				$mediaAlbum = NULL;
			}
			if ($mediaAlbum && $mediaAlbumsUids === array() && !$this->checkAlbumPid($mediaAlbum)) {
				$mediaAlbum = NULL;
			}
			if (!$mediaAlbum) {
				$this->pageNotFound(LocalizationUtility::translate('no_album_found', $this->extensionName));
			}
		}

		$mediaAlbums = $this->mediaAlbumRepository->findByParentalbum($mediaAlbum, $mediaAlbumsUids, $useAlbumFilterAsExclude, $this->settings['list']['hideEmptyAlbums']);

		// when only 1 album skip gallery view
		if ($mediaAlbum === NULL && !empty($this->settings['list']['skipListWhenOnlyOneAlbum']) && count($mediaAlbums) === 1) {
			$mediaAlbum = $mediaAlbums[0];
			$mediaAlbums = $this->mediaAlbumRepository->findByParentalbum($mediaAlbum, $mediaAlbumsUids, $useAlbumFilterAsExclude, $this->settings['list']['hideEmptyAlbums']);
			$showBackLink = FALSE;
		}

		$this->view->assign('showBackLink', $showBackLink);
		$this->view->assign('mediaAlbums', $mediaAlbums);
		$this->view->assign('mediaAlbum', $mediaAlbum);
	}

	/**
	 * FlatList Action
	 * Displays a (one-dimensional, flattened) list of albums
	 *
	 * @param int $mediaAlbum (this is not directly mapped to an object to handle 404 on our own)
	 * @return void
	 */
	public function flatListAction($mediaAlbum = 0) {
		$showBackLink = TRUE;
		if ($mediaAlbum) {
			// if an album is given, display it
			$mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbum);
			if (!$mediaAlbum) {
				$this->pageNotFound(LocalizationUtility::translate('no_album_found', $this->extensionName));
			}
			$this->view->assign('displayMode', 'album');
			$this->view->assign('mediaAlbum', $mediaAlbum);
		} else {
			// display the album list
			$mediaAlbums = $this->mediaAlbumRepository->findAll($this->settings['list']['hideEmptyAlbums'], $this->settings['list']['flat']['orderBy'], $this->settings['list']['flat']['orderDirection']);
			$this->view->assign('displayMode', 'flatList');
			$this->view->assign('mediaAlbums', $mediaAlbums);
			$showBackLink = FALSE;
		}
		$this->view->assign('showBackLink', $showBackLink);
	}

	/**
	 * Show single Album (defined in FlexForm/TS) Action
	 * As showAlbumAction() displays any album by the given param this function gets its value from TS/Felxform
	 * This could be merged with showAlbumAction() if there is a way to determine which switchableControllerActions is defined in Felxform.
	 *
	 * @return void
	 */
	public function showAlbumByConfigAction() {
		// get all request arguments (e.g. pagination widget)
		$arguments = $this->request->getArguments();
		// set album id from settings
		$arguments['mediaAlbum'] = $this->settings['mediaAlbum'];
		$this->forward('showAlbum', NULL, NULL, $arguments);
	}

	/**
	 * Show single Album Action
	 *
	 * @param int $mediaAlbum (this is not directly mapped to an object to handle 404 on our own)
	 * @return void
	 */
	public function showAlbumAction($mediaAlbum = NULL) {
		$mediaAlbum = (int)$mediaAlbum ?: NULL;
		if (empty($mediaAlbum)) {
			$mediaAlbum = (int)$this->settings['mediaAlbum'];
		}
		$mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbum);
		if (!$mediaAlbum) {
			$this->pageNotFound(LocalizationUtility::translate('no_album_found', $this->extensionName));
		}
		$this->view->assign('mediaAlbum', $mediaAlbum);
		$this->view->assign('showBackLink', FALSE);
	}

	/**
	 * Show single media asset from album
	 *
	 * @param \MiniFranske\FsMediaGallery\Domain\Model\MediaAlbum $mediaAlbum
	 * @param int $mediaAssetUid
	 * @ignorevalidation
	 */
	public function showAssetAction(MediaAlbum $mediaAlbum, $mediaAssetUid) {
		/** @var $mediaAsset \TYPO3\CMS\Core\Resource\File */
		if (!$mediaAsset = $mediaAlbum->getAssetByUid($mediaAssetUid)) {
			$message = LocalizationUtility::translate('asset_not_found', $this->extensionName);
			$this->pageNotFound((empty($message) ? 'Asset not found.' : $message));
		}
		$this->view->assign('mediaAlbum', $mediaAlbum);
		$this->view->assign('mediaAsset', $mediaAsset);
	}

	/**
	 * Show random media asset
	 *
	 * @return void
	 */
	public function randomAssetAction() {
		$filterByUids = GeneralUtility::trimExplode(',', $this->settings['mediaAlbums'], TRUE);
		$mediaAlbum = $this->mediaAlbumRepository->findRandom(NULL, $filterByUids, !empty($this->settings['useAlbumFilterAsExclude']));
		$this->view->assign('mediaAlbum', $mediaAlbum);
	}

	/**
	 * If there were validation errors, we don't want to write details like
	 * "An error occurred while trying to call Tx_Community_Controller_UserController->updateAction()"
	 *
	 * @return string|boolean The flash message or FALSE if no flash message should be set
	 */
	protected function getErrorFlashMessage() {
		return FALSE;
	}

	/**
	 * Check if album pid is in allowed storage
	 *
	 * @param MediaAlbum $mediaAlbum
	 * @return bool
	 */
	protected function checkAlbumPid(MediaAlbum $mediaAlbum) {
		$frameworkConfiguration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
		$allowedStoragePages = GeneralUtility::trimExplode(
			',',
			$frameworkConfiguration['persistence']['storagePid']
		);
		if (in_array($mediaAlbum->getPid(), $allowedStoragePages)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Page not found wrapper
	 *
	 * @param string $message
	 * @throws StopActionException
	 */
	protected function pageNotFound($message) {
		if (!empty($GLOBALS['TSFE'])) {
			$GLOBALS['TSFE']->pageNotFoundAndExit($message);
		} else {
			echo $message;
		}
		throw new StopActionException();
	}

}
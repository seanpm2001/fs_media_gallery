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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * MediaAlbumController
 */
class MediaAlbumController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * mediaAlbumRepository
	 *
	 * @var \MiniFranske\FsMediaGallery\Domain\Repository\MediaAlbumRepository
	 * @inject
	 */
	protected $mediaAlbumRepository;

	/**
	 * action show
	 *
	 * @param int $mediaAlbum (this is not directly mapped to a object to handle 404 on our own)
	 * @return void
	 */
	public function showAction($mediaAlbum = 0) {
		$mediaAlbums = NULL;
		$mediaAlbum = (int)$mediaAlbum ?: NULL;
		$mediaGalleryUids = array();
		$showBackLink = TRUE;

		if(!empty($this->settings['mediagalleries'])) {
			$mediaGalleryUids = GeneralUtility::trimExplode(',', $this->settings['mediagalleries']);
		}

		if ($mediaAlbum) {
			/** @var MediaAlbum $mediaAlbum */
			$mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbum);
			if ($mediaAlbum && count($mediaGalleryUids) && !in_array($mediaAlbum->getUid(), $mediaGalleryUids)) {
				$mediaAlbum = NULL;
			}
			if ($mediaAlbum && count($mediaGalleryUids) === 0 && !$this->checkAlbumPid($mediaAlbum)) {
				$mediaAlbum = NULL;
			}
			if (!$mediaAlbum) {
				$this->pageNotFound(LocalizationUtility::translate('no_album_found', $this->extensionName));
			}
		}

		$mediaAlbums = $this->getAlbums($mediaAlbum, $mediaGalleryUids);

		// when only 1 album skip gallery view
		if ($mediaAlbum === NULL && !empty($this->settings['skipGalleryWhenOneAlbum']) && count($mediaAlbums) === 1) {
			$mediaAlbum = $mediaAlbums[0];
			$mediaAlbums = $this->getAlbums($mediaAlbum, $mediaGalleryUids);
			$showBackLink = FALSE;
		}

		$this->view->assign('showBackLink', $showBackLink);
		$this->view->assign('mediaAlbums', $mediaAlbums);
		$this->view->assign('mediaAlbum', $mediaAlbum);
	}

	/**
	 * Get all sub-albums of a given album
	 *
	 * @param $parentAlbum
	 * @param array $allowedAlbumUids
	 * @return array
	 */
	protected function getAlbums($parentAlbum, $allowedAlbumUids = array()) {

		$mediaAlbums = array();
		$_mediaAlbums = $this->mediaAlbumRepository->findByParentalbum($parentAlbum ?: FALSE);

		// filter albums by allowed uids
		if (count($allowedAlbumUids)) {
			foreach ($_mediaAlbums as $_album) {
				if (in_array($_album->getUid(), $allowedAlbumUids)) {
					$mediaAlbums[] = $_album;
				}
			}
		} else {
			$mediaAlbums = $_mediaAlbums;
		}

		return $mediaAlbums;
	}

	/**
	 * Show single image from album
	 *
	 * @param \MiniFranske\FsMediaGallery\Domain\Model\MediaAlbum $mediaAlbum
	 * @param int $mediaItemUid
	 * @ignorevalidation
	 */
	public function showImageAction(MediaAlbum $mediaAlbum, $mediaItemUid) {
		$this->view->assign('mediaAlbum', $mediaAlbum);
		$this->view->assign('mediaItem', \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getFileObject($mediaItemUid));
	}

	/**
	 * Show random image
	 *
	 * @return void
	 */
	public function randomImageAction() {
		$mediaAlbums = GeneralUtility::trimExplode(',', $this->settings['mediagalleries'], TRUE);
		$mediaAlbum = $this->mediaAlbumRepository->findByUid($mediaAlbums[rand(1,count($mediaAlbums))-1]);
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
		$frameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
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
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	protected function pageNotFound($message) {
		if (!empty($GLOBALS['TSFE'])) {
			$GLOBALS['TSFE']->pageNotFoundAndExit($message);
		} else {
			echo $message;
		}
		throw new \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException();
	}
}
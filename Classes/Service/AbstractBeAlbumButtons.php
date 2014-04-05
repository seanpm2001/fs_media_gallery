<?php
namespace MiniFranske\FsMediaGallery\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 20014 Frans Saris <franssaris@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract utility class for classes that want to add album add/edit buttons
 * somewhere like a ClickMenuOptions class.
 */
abstract class AbstractBeAlbumButtons {

	/**
	 * Generate album add/edit buttons for click menu or toolbar
	 *
	 * @param string $combinedIdentifier
	 * @return array
	 */
	protected function generateButtons($combinedIdentifier) {
		$buttons = array();

		/** @var $file \TYPO3\CMS\Core\Resource\Folder */
		$folder = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()
			->retrieveFileOrFolderObject($combinedIdentifier);

		if ($folder && $folder instanceof Folder && in_array(
				$folder->getRole(),
				array(Folder::ROLE_DEFAULT, Folder::ROLE_USERUPLOAD)
			)
		) {

			/** @var \MiniFranske\FsMediaGallery\Service\Utility $utility */
			$utility = GeneralUtility::makeInstance('MiniFranske\\FsMediaGallery\\Service\\Utility');
			$mediaFolders = $utility->getStorageFolders();

			if (count($mediaFolders)) {

				/** @var \TYPO3\CMS\Core\Charset\CharsetConverter $charsetConverter */
				$charsetConverter = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Charset\\CharsetConverter');
				/** @var $fileCollectionRepository \MiniFranske\FsMediaGallery\Domain\Repository\FileCollectionRepository * */
				$fileCollectionRepository = new \MiniFranske\FsMediaGallery\Domain\Repository\FileCollectionRepository();
				$collections = $fileCollectionRepository->findByStorageAndFolder(
					$folder->getStorage()->getUid(),
					$folder->getIdentifier(),
					array_keys($mediaFolders)
				);

				foreach ($collections as $collection) {
					$buttons[] = $this->createLink(
						sprintf($this->sL('editAlbum'), $collection->getTitle()),
						sprintf($this->sL('editAlbum'), $charsetConverter->crop('utf-8', $collection->getTitle(), 12, '...')),
						IconUtility::getSpriteIcon('extensions-fs_media_gallery-edit-album'),
						"alt_doc.php?edit[sys_file_collection][" . $collection->getUid() . "]=edit"
					);
				}

				if (!count($collections)) {
					foreach ($mediaFolders as $uid => $title) {
						$buttons[] = $this->createLink(
							sprintf($this->sL('createAlbumIn'), $title),
							sprintf($this->sL('createAlbumIn'), $charsetConverter->crop('utf-8', $title, 12, '...')),
							IconUtility::getSpriteIcon('extensions-fs_media_gallery-add-album'),
							"alt_doc.php?edit[sys_file_collection][" . $uid . "]=new&defVals[sys_file_collection][title]=" . ucfirst(trim(str_replace('_', ' ', $folder->getName()))) . "&defVals[sys_file_collection][storage]=" . $folder->getStorage()->getUid() . "&defVals[sys_file_collection][folder]=" . $folder->getIdentifier() . "&defVals[sys_file_collection][type]=folder"
						);
					}
				}

			// show hint button for admin users
			// todo: make this better so it can also be used for editors with enough rights to create a storageFolder
			} elseif ($GLOBALS['BE_USER']->isAdmin()) {
				$buttons[] = $this->createLink(
					$this->sL('createAlbum'),
					$this->sL('createAlbum'),
					IconUtility::getSpriteIcon('extensions-fs_media_gallery-add-album'),
					'alert("' . GeneralUtility::slashJS($this->sL('firstCreateStorageFolder')) . '");',
					FALSE
				);
			}
		}
		return $buttons;
	}

	/**
	 * Create link/button
	 *
	 * @param string $title
	 * @param string $shortTitle
	 * @param string $icon
	 * @param string $url
	 * @param bool $addReturnUrl
	 * @return string
	 */
	abstract protected function createLink($title, $shortTitle, $icon, $url, $addReturnUrl = TRUE);

	/**
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLangService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * Get language string
	 *
	 * @param string $key
	 * @param string $languageFile
	 * @return string
	 */
	protected function sL($key, $languageFile = 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf') {
		return $this->getLangService()->sL($languageFile . ':' . $key);
	}
}
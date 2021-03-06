<?php

/**
 * @file classes/site/VersionDAO.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class VersionDAO
 * @ingroup site
 * @see Version
 *
 * @brief Operations for retrieving and modifying Version objects.
 */

// $Id$


import('site.Version');

class VersionDAO extends DAO {
	/**
	 * Retrieve the current version.
	 * @param $product string
	 * @param $isUpgrade boolean
	 * @param $isPlugin boolean
	 * @return Version
	 */
	function &getCurrentVersion($product = null, $isUpgrade = false, $isPlugin = false) {
		if(!$product) {
			$application = PKPApplication::getApplication();
			$product = $application->getName();
		}

		if (!$isPlugin) {
			$result =& $this->retrieve(
				'SELECT * FROM versions WHERE current = 1'
			);
			if ($result->RecordCount() != 0) {
				$oldVersion =& $this->_returnVersionFromRow($result->GetRowAssoc(false));
				$oldVersionType = $oldVersion->getProductType();
				if (isset($oldVersion) &&  $oldVersion->compare('2.3.0') < 0 && $oldVersion->compare('2.0.0') > 0) $isUpgrade = true;
			}
		}

		if (!$isUpgrade) {
			$result =& $this->retrieve(
				'SELECT * FROM versions WHERE current = 1 AND product = ?',
				array($product)
			);
			if ($result->RecordCount() != 0) {
				$returner =& $this->_returnVersionFromRow($result->GetRowAssoc(false));
			}
		} else {
			$returner =& $oldVersion;
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Retrieve the complete version history, ordered by date (most recent first).
	 * @param $product string
	 * @return array Versions
	 */
	function &getVersionHistory($product = null) {
		$versions = array();

		if(!$product) {
			$application = PKPApplication::getApplication();
			$product = $application->getName();
		}

		$result =& $this->retrieve(
			'SELECT * FROM versions WHERE product = ? ORDER BY date_installed DESC',
			array($product)
		);

		while (!$result->EOF) {
			$versions[] = $this->_returnVersionFromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $versions;
	}

	/**
	 * Internal function to return a Version object from a row.
	 * @param $row array
	 * @return Version
	 */
	function &_returnVersionFromRow(&$row) {
		$version = new Version();
		$version->setMajor($row['major']);
		$version->setMinor($row['minor']);
		$version->setRevision($row['revision']);
		$version->setBuild($row['build']);
		$version->setDateInstalled($this->datetimeFromDB($row['date_installed']));
		$version->setCurrent($row['current']);
		$version->setProductType(isset($row['product_type']) ? $row['product_type'] : null);
		$version->setProduct(isset($row['product']) ? $row['product'] : null);

		HookRegistry::call('VersionDAO::_returnVersionFromRow', array(&$version, &$row));

		return $version;
	}

	/**
	 * Insert a new version.
	 * @param $version Version
	 */
	function insertVersion(&$version) {
		if ($version->getCurrent()) {
			// Version to insert is the new current, reset old current
			$this->update('UPDATE versions SET current = 0 WHERE current = 1 AND product = ?', $version->getProduct());
		}
		if ($version->getDateInstalled() == null) {
			$version->setDateInstalled(Core::getCurrentDate());
		}

		return $this->update(
			sprintf('INSERT INTO versions
				(major, minor, revision, build, date_installed, current, product_type, product)
				VALUES
				(?, ?, ?, ?, %s, ?, ?, ?)',
				$this->datetimeToDB($version->getDateInstalled())),
			array(
				(int) $version->getMajor(),
				(int) $version->getMinor(),
				(int) $version->getRevision(),
				(int) $version->getBuild(),
				(int) $version->getCurrent(),
				$version->getProductType(),
				$version->getProduct()
			)
		);
	}

	/**
	 * Retrieve all products.
	 * @param $productType string filter by product type (e.g. plugins, core)
	 * @return DAOResultFactory containing matching versions
	 */
	function &getVersions($productType = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM versions WHERE current = 1 ' .
			($productType ? 'AND product_type LIKE ? ' : '') .
			'ORDER BY product', $productType ? $productType . '%' : ''
		);

		$returner = new DAOResultFactory($result, $this, '_returnVersionFromRow');
		return $returner;
	}

	/**
	 * Disable a product by setting its 'current' column to 0
	 * @param $product string
	 */
	function disableVersion($product) {
		if ($product == 'NULL') {
			$this->update(
				'UPDATE versions SET current = 0 WHERE current = 1 AND product IS NULL'
			);
		} else {
			$this->update(
				'UPDATE versions SET current = 0 WHERE current = 1 AND product = ?',
				array($product)
			);
		}
	}
}

?>

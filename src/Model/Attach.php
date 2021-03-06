<?php

/**
 * @file src/Model/Attach.php
 * @brief This file contains the Attach class for database interface
 */
namespace Friendica\Model;

use Friendica\BaseObject;
use Friendica\Core\StorageManager;
use Friendica\Core\System;
use Friendica\Database\DBA;
use Friendica\Database\DBStructure;
use Friendica\Model\Storage\IStorage;
use Friendica\Object\Image;
use Friendica\Util\DateTimeFormat;
use Friendica\Util\Mimetype;
use Friendica\Util\Security;

/**
 * Class to handle attach dabatase table
 */
class Attach extends BaseObject
{

	/**
	 * @brief Return a list of fields that are associated with the attach table
	 *
	 * @return array field list
	 * @throws \Exception
	 */
	private static function getFields()
	{
		$allfields = DBStructure::definition(self::getApp()->getBasePath(), false);
		$fields = array_keys($allfields['attach']['fields']);
		array_splice($fields, array_search('data', $fields), 1);
		return $fields;
	}

	/**
	 * @brief Select rows from the attach table
	 *
	 * @param array $fields     Array of selected fields, empty for all
	 * @param array $conditions Array of fields for conditions
	 * @param array $params     Array of several parameters
	 *
	 * @return boolean|array
	 *
	 * @throws \Exception
	 * @see   \Friendica\Database\DBA::select
	 */
	public static function select(array $fields = [], array $conditions = [], array $params = [])
	{
		if (empty($fields)) {
			$fields = self::getFields();
		}

		$r = DBA::select('attach', $fields, $conditions, $params);
		return DBA::toArray($r);
	}

	/**
	 * @brief Retrieve a single record from the attach table
	 *
	 * @param array $fields     Array of selected fields, empty for all
	 * @param array $conditions Array of fields for conditions
	 * @param array $params     Array of several parameters
	 *
	 * @return bool|array
	 *
	 * @throws \Exception
	 * @see   \Friendica\Database\DBA::select
	 */
	public static function selectFirst(array $fields = [], array $conditions = [], array $params = [])
	{
		if (empty($fields)) {
			$fields = self::getFields();
		}

		return DBA::selectFirst('attach', $fields, $conditions, $params);
	}

	/**
	 * @brief Check if attachment with given conditions exists
	 *
	 * @param array $conditions Array of extra conditions
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	public static function exists(array $conditions)
	{
		return DBA::exists('attach', $conditions);
	}

	/**
	 * @brief Retrive a single record given the ID
	 *
	 * @param int $id Row id of the record
	 *
	 * @return bool|array
	 *
	 * @throws \Exception
	 * @see   \Friendica\Database\DBA::select
	 */
	public static function getById($id)
	{
		return self::selectFirst([], ['id' => $id]);
	}

	/**
	 * @brief Retrive a single record given the ID
	 *
	 * @param int $id Row id of the record
	 *
	 * @return bool|array
	 *
	 * @throws \Exception
	 * @see   \Friendica\Database\DBA::select
	 */
	public static function getByIdWithPermission($id)
	{
		$r = self::selectFirst(['uid'], ['id' => $id]);
		if ($r === false) {
			return false;
		}

		$sql_acl = Security::getPermissionsSQLByUserId($r['uid']);

		$conditions = [
			'`id` = ?' . $sql_acl,
			$id
		];

		$item = self::selectFirst([], $conditions);

		return $item;
	}

	/**
	 * @brief Get file data for given row id. null if row id does not exist
	 *
	 * @param array $item Attachment data. Needs at least 'id', 'backend-class', 'backend-ref'
	 *
	 * @return string  file data
	 * @throws \Exception
	 */
	public static function getData($item)
	{
		if ($item['backend-class'] == '') {
			// legacy data storage in 'data' column
			$i = self::selectFirst(['data'], ['id' => $item['id']]);
			if ($i === false) {
				return null;
			}
			return $i['data'];
		} else {
			$backendClass = $item['backend-class'];
			$backendRef = $item['backend-ref'];
			return $backendClass::get($backendRef);
		}
	}

	/**
	 * @brief Store new file metadata in db and binary in default backend
	 *
	 * @param string  $data      Binary data
	 * @param integer $uid       User ID
	 * @param string  $filename  Filename
	 * @param string  $filetype  Mimetype. optional, default = ''
	 * @param integer $filesize  File size in bytes. optional, default = null
	 * @param string  $allow_cid Permissions, allowed contacts. optional, default = ''
	 * @param string  $allow_gid Permissions, allowed groups. optional, default = ''
	 * @param string  $deny_cid  Permissions, denied contacts.optional, default = ''
	 * @param string  $deny_gid  Permissions, denied greoup.optional, default = ''
	 *
	 * @return boolean/integer Row id on success, False on errors
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function store($data, $uid, $filename, $filetype = '' , $filesize = null, $allow_cid = '', $allow_gid = '', $deny_cid = '', $deny_gid = '')
	{
		if ($filetype === '') {
			$filetype = Mimetype::getContentType($filename);
		}

		if (is_null($filesize)) {
			$filesize = strlen($data);
		}

		/** @var IStorage $backend_class */
		$backend_class = StorageManager::getBackend();
		$backend_ref = '';
		if ($backend_class !== '') {
			$backend_ref = $backend_class::put($data);
			$data = '';
		}

		$hash = System::createGUID(64);
		$created = DateTimeFormat::utcNow();

		$fields = [
			'uid' => $uid,
			'hash' => $hash,
			'filename' => $filename,
			'filetype' => $filetype,
			'filesize' => $filesize,
			'data' => $data,
			'created' => $created,
			'edited' => $created,
			'allow_cid' => $allow_cid,
			'allow_gid' => $allow_gid,
			'deny_cid' => $deny_cid,
			'deny_gid' => $deny_gid,
			'backend-class' => $backend_class,
			'backend-ref' => $backend_ref
		];

		$r = DBA::insert('attach', $fields);
		if ($r === true) {
			return DBA::lastInsertId();
		}
		return $r;
	}

	/**
	 * @brief Store new file metadata in db and binary in default backend from existing file
	 *
	 * @param        $src
	 * @param        $uid
	 * @param string $filename
	 * @param string $allow_cid
	 * @param string $allow_gid
	 * @param string $deny_cid
	 * @param string $deny_gid
	 * @return boolean True on success
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public static function storeFile($src, $uid, $filename = '', $allow_cid = '', $allow_gid = '', $deny_cid = '', $deny_gid = '')
	{
		if ($filename === '') {
			$filename = basename($src);
		}

		$data = @file_get_contents($src);

		return self::store($data, $uid, $filename, '', null, $allow_cid, $allow_gid,  $deny_cid, $deny_gid);
	}


	/**
	 * @brief Update an attached file
	 *
	 * @param array         $fields     Contains the fields that are updated
	 * @param array         $conditions Condition array with the key values
	 * @param Image         $img        Image data to update. Optional, default null.
	 * @param array|boolean $old_fields Array with the old field values that are about to be replaced (true = update on duplicate)
	 *
	 * @return boolean  Was the update successful?
	 *
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 * @see   \Friendica\Database\DBA::update
	 */
	public static function update($fields, $conditions, Image $img = null, array $old_fields = [])
	{
		if (!is_null($img)) {
			// get items to update
			$items = self::select(['backend-class','backend-ref'], $conditions);

			foreach($items as $item) {
				/** @var IStorage $backend_class */
				$backend_class = (string)$item['backend-class'];
				if ($backend_class !== '') {
					$fields['backend-ref'] = $backend_class::put($img->asString(), $item['backend-ref']);
				} else {
					$fields['data'] = $img->asString();
				}
			}
		}

		$fields['edited'] = DateTimeFormat::utcNow();

		return DBA::update('attach', $fields, $conditions, $old_fields);
	}


	/**
	 * @brief Delete info from table and data from storage
	 *
	 * @param array $conditions Field condition(s)
	 * @param array $options    Options array, Optional
	 *
	 * @return boolean
	 *
	 * @throws \Exception
	 * @see   \Friendica\Database\DBA::delete
	 */
	public static function delete(array $conditions, array $options = [])
	{
		// get items to delete data info
		$items = self::select(['backend-class','backend-ref'], $conditions);

		foreach($items as $item) {
			/** @var IStorage $backend_class */
			$backend_class = (string)$item['backend-class'];
			if ($backend_class !== '') {
				$backend_class::delete($item['backend-ref']);
			}
		}

		return DBA::delete('attach', $conditions, $options);
	}
}

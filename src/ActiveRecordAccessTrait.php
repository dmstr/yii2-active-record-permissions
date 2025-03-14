<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2016 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\activeRecordPermissions;

use Yii;
use dmstr\db\exceptions\UnsupportedDbException;
use yii\console\Application as ConsoleApplication;
use yii\helpers\StringHelper;

/**
 * Trait ActiveRecordAccessTrait
 *
 * @package dmstr\db\traits
 * @author Christopher Stebe <c.stebe@herzogkommunikation.de>
 */
trait ActiveRecordAccessTrait
{
    /**
     * Use session flash messages
     * @var bool
     */
    public static $enableFlashMessages = true;

    /**
     * Active find, beforeSave, beforeDelete
     * @var bool
     */
    public static $activeAccessTrait = true;

    /**
     * Enable/disable recursive role check
     * @var bool
     */
    public static $enableRecursiveRoles = false;

    /**
     * Public / all access
     * @var string
     */
    public static $_all = '*';

    /**
     * Role that can get/set all AuthItems, default 'Admin'
     * can be defined via app::params
     *
     * @var string
     */
    public static $allAuthItemsRole = 'Admin';

    /**
     * Admin Role that returns true for all access checks, default 'Admin'
     * can be defined via app::params
     *
     * @var string
     */
    public static $adminRole = 'Admin';

    /**
     * @return array with access field names
     */
    public static function accessColumnAttributes()
    {
        // use prefix to avoid ambigious column names
        $prefix = self::getTableSchema()->name;
        return [
            'owner'  => "{$prefix}.access_owner",
            'read'   => "{$prefix}.access_read",
            'update' => "{$prefix}.access_update",
            'delete' => "{$prefix}.access_delete",
            'domain' => "{$prefix}.access_domain",
        ];
    }

    /**
     * Method should be overwritten if the access_owner reference is different from the primary key of the user
     * identity. User identity is already evaluated at this point, which means that this method is not or should not be
     * called at any time if the user is a guest.
     */
    protected static function currentUserId(): string
    {
        return (string)Yii::$app->getUser()->getId();
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        /** @var $query \yii\db\ActiveQuery */
        $query = parent::find();

        // disabled access behavior in console applications and for role 'Admin'
        if (Yii::$app instanceof ConsoleApplication || Yii::$app->user->can(self::getAdminRole())) {
            return $query;
        }

        $accessOwner  = self::accessColumnAttributes()['owner'];
        $accessRead   = self::accessColumnAttributes()['read'];
        $accessDomain = self::accessColumnAttributes()['domain'];

        if (self::$activeAccessTrait) {

            // access owner check only if attribute exists and user is logged in
            $accessOwnerCheck = false;
            if ($accessOwner && !\Yii::$app->user->isGuest) {
                $accessOwnerCheck = true;
                $query->where([$accessOwner => static::currentUserId()]);
            }

            // access read check
            if ($accessRead) {
                $queryType = ($accessOwnerCheck) ? 'orWhere' : 'where';
                $authItems = implode(',', array_keys(self::getUsersAuthItems()));
                $checkInSetQuery = self::getInSetQueryPart($accessRead, $authItems);
                $query->$queryType($checkInSetQuery);
            }

            // access domain check
            if ($accessDomain) {
                $query->andWhere([$accessDomain => [\Yii::$app->language, self::$_all]]);
            }
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {

            // disabled access behavior in console applications
            if (Yii::$app instanceof ConsoleApplication) {
                return true;
            }

            if (self::$activeAccessTrait) {
                if ($insert) {
                    // INSERT record: return true for new records
                    $accessOwner = self::accessColumnAttributes()['owner'];
                    if ($accessOwner && !\Yii::$app->user->isGuest) {
                        $this->{$this->getSchemaProperty($accessOwner)} = static::currentUserId();
                    }
                } else {

                    // skip check if model has no changes
                    if (empty($this->getDirtyAttributes())) {
                        Yii::trace('Model has no changes, skipping permission check', __METHOD__);
                        return true;
                    }

                    // UPDATE record
                    $accessUpdate = self::accessColumnAttributes()['update'];
                    if ($accessUpdate) {
                        if (!$this->hasPermission($accessUpdate)) {
                            $this->addAccessError('update', $accessUpdate);
                            return false;
                        }
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {

            // disabled access behavior in console applications
            if (Yii::$app instanceof ConsoleApplication) {
                return true;
            }

            if (self::$activeAccessTrait) {
                $accessDelete = self::accessColumnAttributes()['delete'];
                if ($accessDelete && !$this->hasPermission($accessDelete)) {
                    $this->addAccessError('delete', $accessDelete);
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return array to identify all access
     */
    public static function allAccess()
    {
        return [self::$_all => self::$_all];
    }

    /**
     * Returns roles *assigned* to current user or all roles for admin
     * @return array with item names
     */
    public static function getUsersAuthItems()
    {
        static $items;

        // Public auth item, default
        $publicAuthItem = self::allAccess();


        if (\Yii::$app instanceof yii\web\Application) {

            if (!$items) {

                \Yii::trace("Get and check UsersAuthItems", __METHOD__);

                // auth manager
                $authManager = \Yii::$app->authManager;
                $authItems = [];

                $allRoles = $authManager->getRoles();

                // when user is 'Admin' use all roles
                if (Yii::$app->user->can(self::getAllAuthItemsRole())) {
                    $roles = $allRoles;
                } else {
                    if (!static::isEnabledRecursiveRoles()) {
                        // only direct assigned roles
                        $roles = $authManager->getRolesByUser(Yii::$app->user->id);
                    } else {
                        // check all roles
                        $roles = [];
                        foreach ($allRoles as $roleItem) {
                            $roleName = $roleItem->name;
                            if (Yii::$app->user->can($roleName)) {
                                $roles[$roleName] = $roleItem;
                            }
                        }
                    }
                }


                foreach ($roles as $name => $item) {
                    $authItems[$name] = $name . ' (' . $item->description . ')';
                }

                $items = array_merge($publicAuthItem, $authItems);
                asort($items);
            }

            return $items;
        }

        return $publicAuthItem;
    }

    /**
     * get allAuthItemsRole from $app->params or static property
     *
     * @return string
     */
    public static function getAllAuthItemsRole()
    {
        return Yii::$app->params['ActiveRecordAccessTrait']['allAuthItemsRole'] ?? static::$allAuthItemsRole;
    }

    /**
     * get adminRole from $app->params or static property
     *
     * @return string
     */
    public static function getAdminRole()
    {
        return Yii::$app->params['ActiveRecordAccessTrait']['adminRole'] ?? static::$adminRole;
    }

    /**
     * @return bool|mixed
     */
    public static function isEnabledRecursiveRoles () {
        return Yii::$app->params['ActiveRecordAccessTrait']['enableRecursiveRoles'] ?? static::$enableRecursiveRoles;
    }

    public static function getDefaultAccessDomain() {
        // return first found permission
        $AuthManager = \Yii::$app->authManager;
        $permissions = $AuthManager->getPermissionsByUser(Yii::$app->user->id);
        foreach ($permissions as $name => $Permission) {
            if (StringHelper::startsWith($name, 'access.defaults.domain:')) {
                $data = explode(':', $name);
                if (empty($data[1])) {
                    Yii::warning("Invalid domain access permission '$name'", __METHOD__);
                    continue;
                }

                // map global to '*' since it is not allowed as a permission name (usuario)
                return ($data[1] == 'global') ? self::$_all : $data[1];
            }
        }
        return Yii::$app->language;
    }

    /**
     * @return null,string default access permission for user
     */
    public static function getDefaultAccessUpdateDelete() {

        // allow setting `null` for eg. Admins
        if (Yii::$app->user->can('access.defaults.updateDelete:null')) {
            return null;
        }

        // return first found permission
        $authManager = \Yii::$app->authManager;
        // maybe(tm) we should get only these permissions from the user?
        // but then we MUST check that the user is logged in?
        // $permissions = $authManager->getPermissionsByUser($user_id);
        $permissions = $authManager->getPermissions();
        foreach ($permissions as $name => $Permission) {
            if (StringHelper::startsWith($name, 'access.defaults.updateDelete:')) {
                $data = explode(':', $name);
                if (empty($data[1])) {
                    Yii::warning("Invalid update/delete access permission '$name'", __METHOD__);
                    continue;
                }
                if (Yii::$app->user->can($name)) {
                    return $data[1];
                }
            }
        }
        return null;
    }


    /**
     * Decode access column by action from csv to array
     *
     * @param string $action
     * @param array $authItems
     *
     * @return string|null
     */
    public function authItemArrayToString($action, array $authItems)
    {
        if (!in_array($action, self::accessColumnAttributes())) {
            return null;
        }

        $this->{$action} = implode(',', array_keys($authItems));
    }

    /**
     * Encode access column by action from csv to array
     * @param $action
     *
     * @return array|null
     */
    public function authItemStringToArray($action)
    {
        if (!in_array($action, self::accessColumnAttributes())) {
            return null;
        }
        $arr = explode(',', $this->$action);
        return array_combine($arr, $arr);
    }

    /**
     * Check permission for record
     *
     * @param null $action
     *
     * @return bool
     */
    public function hasPermission($action)
    {
        // return false, if action is not valid
        # TODO: Improve $action param (don't use/check prefix)
        #if (!in_array($action, self::accessColumnAttributes())) {
        #    return false;
        #}

        // always true for admins
        if (\Yii::$app->user->can(self::getAdminRole())) {
            return true;
        }

        // owner check (has all permissions)
        $accessOwner  = self::accessColumnAttributes()['owner'];
        if ($accessOwner) {
            if (!\Yii::$app->user->isGuest && $this->getOldAttribute($this->getSchemaProperty($accessOwner)) == static::currentUserId()) {
                return true;
            }
        }

        // allow, if permission is "*"
        $column =  $this->getSchemaProperty($action);
        if ($this->getOldAttribute($column) === self::$_all) {
            return true;
        }

        // check assigned permissions
        return Yii::$app->user->can($this->getOldAttribute($column));
    }

    /**
     * - Set error flash for controller action id
     * - Add error message to access attribute
     * - Write error log
     *
     * @param string $action update|delete
     * @param string $attribute
     */
    private function addAccessError($action, $attribute)
    {
        // the error message
        $msg = \Yii::t(
            'app',
            'You are not allowed to {0} record #{1}',
            [$action, $this->primaryKey]
        );

        if (self::$enableFlashMessages) {
            \Yii::$app->session->addFlash('error', $msg);
        }
        $this->addError($attribute, $msg);
        \Yii::info('User ID: #' . \Yii::$app->user->id . ' | ' . $msg, get_called_class());
    }

    /**
     * Return correct part of check in set  query for current DB
     * @param $accessRead
     * @param $authItems
     * @return string|array
     */
    private static function getInSetQueryPart($accessRead, $authItems)
    {
        $dbName = Yii::$app->db->getDriverName();
        switch($dbName) {
            case 'mysql':
                return 'FIND_IN_SET(' . $accessRead . ', "' . $authItems . '") > 0';
            case 'pgsql':
                return [$accessRead => explode(',', $authItems)];
            default:
                throw new UnsupportedDbException('This database is not being supported yet');
        }
    }

    // extract property from table name with schema
    private function getSchemaProperty($schemaProperty){
        // extract property from table name with schema
        if (strstr($schemaProperty, '.')) {
            $prop = substr($schemaProperty, strrpos($schemaProperty, '.') + 1);
        } else {
            $prop = $schemaProperty;
        }
        return $prop;

    }
}

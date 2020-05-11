# Yii 2 Active Record Access Permissions

The package ...

## Installation

## Setup

## General usage

### Example


Traits
---

### [dmstr\activeRecordPermissions\ActiveRecordAccessTrait](https://github.com/dmstr/yii2-db/blob/master/db/traits/ActiveRecordAccessTrait.php)

**Option 1:**

How to equip your active record model with access control

- Use update migration in `db/migrations/m160609_090908_add_access_columns`

    - set all `$tableNames` to be updated and run migration

This migrations adds the available access check columns to your database table(s)

```
'access_owner',
'access_read',
'access_update',
'access_delete',
'access_domain',
```

- Add `use \dmstr\activeRecordPermissions\ActiveRecordAccessTrait;` to your active record model

- *(update your cruds)*

### RBAC permissions

Permissions for selections

- `access.availableDomains:any`

Permissions to set default values

- `access.defaults.accessDomain:global`
- `access.defaults.updateDelete:<ROLE>`

**Option 2:**

Simply override this method in our AR model and set the access fields you have/want to the field names you have/want!

*Default:*
```
public static function accessColumnAttributes()
{
   return [
       'owner'  => 'access_owner',
       'read'   => 'access_read',
       'update' => 'access_update',
       'delete' => 'access_delete',
       'domain' => 'access_domain',
   ];
}
```

*Customize:*
```
public static function accessColumnAttributes()
{
    return [
        'owner'  => 'user_id',			// the column name with owner permissions
        'read'   => 'read_permission',	// the column name with read permissions
        'update' => false, 				// will do no access checks for update
        'delete' => false, 				// will do no access checks for delete
        'domain' => 'language',			// the column name with the access domain permission
    ];
}
```

**:secret: Congrats, you are now ready to manage specific access checks on your active records!**

:bulb: Access options:

- All access option `*`
- specific rbac roles and permissions assignable
    - single or multi
        - `*`
        - `Role1,Role2,Permission1,...`
        
- limit access to specific domain / language
    - `de` or `en`
        
- `Owner` gets all access over other given permissions
    - every active record can have exact one owner right which stands above `access_read`, `access_update`, `access_delete`

Planned updates:
---

- ActiveRecordAccessTrait
    -  in cruds use select2 multi for inputs (domain, read, update, delete)
        - Setter: authItemArrayToString()
        - Getter: authItemStringToArray()
        

---

Built by [dmstr](http://diemeisterei.de)

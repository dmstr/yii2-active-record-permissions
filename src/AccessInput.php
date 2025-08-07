<?php
/**
 * Created by PhpStorm.
 * User: schmunk
 * Date: 09.07.18
 * Time: 11:27
 */

namespace dmstr\activeRecordPermissions;


use dmstr\activeRecordPermissions\ActiveRecordAccessTrait;
use dmstr\modules\redirect\models\ActiveRecord;
use kartik\select2\Select2;
use yii\base\Widget;
use yii\widgets\InputWidget;
use Yii;

class AccessInput extends Widget
{
    public $form;
    /** @var \yii\db\ActiveRecord */
    public $model;

    public $accessFields = ['owner', 'domain', 'read', 'update', 'delete'];

    public $fieldOwner = 'access_owner';
    public $fieldDomain = 'access_domain';
    public $fieldRead = 'access_read';
    public $fieldUpdate = 'access_update';
    public $fieldDelete = 'access_delete';
    public $fieldAppend = 'access_append';

    public function run()
    {
        $return = '';
        $userAuthItems = $this->model::getUsersAuthItems();
        $userDomains = $this->optsAccessDomain();

        $return .= $this->form
            ->field($this->model, $this->fieldOwner)
            ->textInput(['readonly' => true]); // TODO: Check owner in model (has to be the same as current user)

        $disabled = !$this->model->hasPermission($this->fieldUpdate);

        foreach (['domain', 'read', 'update', 'delete'] as $access) {
            $data = $access === 'domain' ? $userDomains : $userAuthItems;
            $fieldName = 'field' . ucfirst($access);

            $value = $this->model->{$this->$fieldName};
            // Check if value set is in data list and add it if its not
            if (!array_key_exists($value, $data)) {
                $data[$value] = Yii::t('app', '{roleName}*', ['roleName' => $value]);
            }

            if ($fieldName === 'fieldDelete' && !empty($this->model->{$this->$fieldName})) {
                $disabled = !$this->model->hasPermission($this->fieldDelete);
            }
//            if ($this->model->getIsNewRecord() || empty($value) || $value === '*') {
//                Yii::debug('nr || empty || *');
//                $disabled = false;
//            } else {
//                Yii::debug($value);
//                $disabled = !$this->model->hasPermission($value);
//            }

            $return .= $this->form->field($this->model, $this->$fieldName)->widget(
                Select2::class,
                [
                    'data' => $data,
                    'options' => ['placeholder' => Yii::t('app', 'Select ...')],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'disabled' => $disabled
                    ]
                ]
            );

        }
        return $return;
    }


    /**
     * @return array Available domains for select
     */
    public function optsAccessDomain()
    {
        $modelClass = get_class($this->model);
        if (Yii::$app->user->can('access.availableDomains:any')) {
            $availableLanguages[$modelClass::$_all] = 'GLOBAL';
            foreach (Yii::$app->urlManager->languages as $availablelanguage) {
                $availableLanguages[mb_strtolower($availablelanguage)] = mb_strtolower($availablelanguage);
            }
        } else {
            // allow current value
            $availableLanguages[$this->model->access_domain] = $this->model->access_domain;
            $availableLanguages[Yii::$app->language] = Yii::$app->language;
        }
        return $availableLanguages;
    }
}

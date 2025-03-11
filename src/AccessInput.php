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
        $disabled = !$this->model->hasPermission('access_update');

        $return .= $this->form
            ->field($this->model, $this->fieldOwner)
            ->textInput(['readonly' => true]); // TODO: Check owner in model (has to be the same as current user)

        foreach (['domain', 'read', 'update', 'delete'] as $access) {
            // save the current value in disabled because we may rewrite it if value is not in data
            $originalDisabled = $disabled;

            $data = $access === 'domain' ? $userDomains : $userAuthItems;
            $fieldName = 'field' . ucfirst($access);

            $vaule = $this->model->{$this->$fieldName};
            // Check if value set is in data list and add it if its not and disable the input
            if (!array_key_exists($vaule, $data)) {
                $data[$vaule] = $vaule;
                $disabled = true;
            }

            $return .= $this->form->field($this->model, $this->$fieldName)->widget(
                Select2::class,
                [
                    'data' => $data,
                    'options' => ['placeholder' => Yii::t('pages', 'Select ...')],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'disabled' => $disabled
                    ]
                ]
            );

            // Reset disabled value if reset from value exists check
            $disabled = $originalDisabled;
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

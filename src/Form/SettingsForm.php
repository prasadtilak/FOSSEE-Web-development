<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {
  protected function getEditableConfigNames() { return ['event_registration.settings']; }
  public function getFormId() { return 'event_registration_admin_settings'; }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('event_registration.settings');
    $form['admin_email'] = [
      '#type' => 'email',
      '#title' => 'Admin Notification Email',
      '#default_value' => $config->get('admin_email'),
    ];
    $form['enable_notifications'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable Admin Notifications',
      '#default_value' => $config->get('enable_notifications'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('event_registration.settings')
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('enable_notifications', $form_state->getValue('enable_notifications'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}

<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RegistrationForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() { return 'event_user_registration_form'; }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $today = date('Y-m-d');
    
    // Fetch categories currently available
    $categories = $this->database->select('event_config', 'e')
      ->fields('e', ['category'])
      ->condition('reg_start', $today, '<=')
      ->condition('reg_end', $today, '>=')
      ->distinct()->execute()->fetchCol();

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => 'Full Name',
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => 'Email Address',
      '#required' => TRUE,
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => 'Category',
      '#options' => array_combine($categories, $categories),
      '#empty_option' => '- Select Category -',
      '#ajax' => [
        'callback' => '::updateDateCallback',
        'wrapper' => 'date-replace-wrapper',
      ],
    ];

    $category_selected = $form_state->getValue('category');

    $form['event_date'] = [
      '#type' => 'select',
      '#title' => 'Event Date',
      '#prefix' => '<div id="date-replace-wrapper">',
      '#suffix' => '</div>',
      '#options' => $this->getDatesByCategory($category_selected),
      '#ajax' => [
        'callback' => '::updateNameCallback',
        'wrapper' => 'name-replace-wrapper',
      ],
    ];

    $form['event_id'] = [
      '#type' => 'select',
      '#title' => 'Event Name',
      '#prefix' => '<div id="name-replace-wrapper">',
      '#suffix' => '</div>',
      '#options' => $this->getNamesByDate($category_selected, $form_state->getValue('event_date')),
    ];

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Register'];
    return $form;
  }

  public function updateDateCallback(array &$form, FormStateInterface $form_state) {
    return $form['event_date'];
  }

  public function updateNameCallback(array &$form, FormStateInterface $form_state) {
    return $form['event_id'];
  }

  private function getDatesByCategory($cat) {
    if (!$cat) return [];
    $dates = $this->database->select('event_config', 'e')
      ->fields('e', ['event_date'])
      ->condition('category', $cat)
      ->execute()->fetchCol();
    return array_combine($dates, $dates);
  }

  private function getNamesByDate($cat, $date) {
    if (!$cat || !$date) return [];
    return $this->database->select('event_config', 'e')
      ->fields('e', ['id', 'event_name'])
      ->condition('category', $cat)
      ->condition('event_date', $date)
      ->execute()->fetchAllKeyed();
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $event_id = $form_state->getValue('event_id');
    
    // Check duplicates
    $exists = $this->database->select('event_registrations', 'r')
      ->condition('email', $email)
      ->condition('event_id', $event_id)
      ->countQuery()->execute()->fetchField();

    if ($exists) {
      $form_state->setErrorByName('email', 'You are already registered for this event.');
    }

    if (preg_match('/[^a-zA-Z0-9 ]/', $form_state->getValue('full_name'))) {
      $form_state->setErrorByName('full_name', 'Special characters are not allowed.');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->insert('event_registrations')
      ->fields([
        'full_name' => $form_state->getValue('full_name'),
        'email' => $form_state->getValue('email'),
        'event_id' => $form_state->getValue('event_id'),
        'created' => time(),
      ])->execute();
    
    \Drupal::messenger()->addMessage('Registration Successful!');
  }
}

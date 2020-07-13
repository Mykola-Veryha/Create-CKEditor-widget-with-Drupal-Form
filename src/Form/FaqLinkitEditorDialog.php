<?php

namespace Drupal\ccom_faq\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\linkit\Form\LinkitEditorDialog;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a linkit dialog for text editors.
 */
class FaqLinkitEditorDialog extends LinkitEditorDialog {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The alias manager that caches alias lookups based on the request.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a form object for linkit dialog.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $editor_storage
   *   The editor storage service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $linkit_profile_storage
   *   The linkit profile storage service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   */
  public function __construct(
    EntityStorageInterface $editor_storage,
    EntityStorageInterface $linkit_profile_storage,
    EntityStorageInterface $node_storage,
    AliasManagerInterface $alias_manager
  ) {
    parent::__construct($editor_storage, $linkit_profile_storage);
    $this->nodeStorage = $node_storage;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpParamsInspection
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('editor'),
      $container->get('entity_type.manager')->getStorage('linkit_profile'),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('path.alias_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'faq_linkit_editor_dialog_form';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    FilterFormat $filter_format = NULL
  ) {
    $form = parent::buildForm($form, $form_state, $filter_format);
    // The default values are set directly from \Drupal::request()->request,
    // provided by the editor plugin opening the dialog.
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['editor_object'])) {
      $input = $user_input['editor_object'];
    }
    elseif (!empty($user_input['attributes'])) {
      $input = $user_input['attributes'];
    }
    else {
      $input = NULL;
    }
    // Hide the href. We will set that on the submit callback.
    $form['attributes']['href']['#access'] = FALSE;
    $form_state->getValues();
    $faq_options = $this->getFaqOptions();
    $form['attributes']['data-nid'] = [
      '#type' => 'select',
      '#title' => t('FAQ'),
      '#default_value' => $input['data-nid'] ?? NULL,
      '#options' => $faq_options,
      '#ajax' => [
        'callback' => '::refreshQuestions',
      ],
    ];

    $faq_nid = $form_state->getValue(['attributes', 'data-nid']);
    if (empty($faq_nid) && !empty($faq_options)) {
      $faq_nid = $input['data-nid'] ?? NULL;
      if (empty($faq_nid)) {
        $faq_nids = array_keys($faq_options);
        $faq_nid = reset($faq_nids);
      }
    }

    $questions_options = $this->getFaqQuestionOptions($faq_nid);
    if (!empty($questions_options)) {
      if (!empty($input['anchor']) && !empty($questions_options[$input['anchor']])) {
        $default_anchor = $input['anchor'];
      }
      else {
        $questions_ids = array_keys($questions_options);
        $default_anchor = reset($questions_ids);
      }
      $form['attributes']['anchor'] = [
        '#type' => 'select',
        '#title' => t('Faq question'),
        '#default_value' => $default_anchor,
        // Rewrite value because there we can have a value from previous Faq questions.
        '#value' => $default_anchor,
        '#options' => $questions_options,
      ];
    }

    $form['attributes']['is_rewrite_title'] = [
      '#type' => 'checkbox',
      '#title' => t('Rewrite link title'),
      '#default_value' => !empty($input['rewrite_title']) ?? FALSE,
    ];

    $form['attributes']['rewrite_title'] = [
      '#type' => 'textfield',
      '#title' => t('Link title'),
      '#default_value' => $input['rewrite_title'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="attributes[is_rewrite_title]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['attributes']['open_in_new_window'] = [
      '#type' => 'checkbox',
      '#title' => t('Open in new window'),
      '#default_value' => isset($input['target']) ? $input['target'] == '_blank' : TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValues();
      if (!empty($values['attributes']['data-nid'])) {
        $faq_options = $this->getFaqOptions();

        // We use nid in the form to find the faq questions
        // but we need send the alias to the CKEditor plugin.
        // @see modules/custom/ccom_faq/js/plugins/faq_linkit/plugin.js::saveCallback
        $values['attributes']['href'] = $this->aliasManager->getAliasByPath(
          '/node/' . $values['attributes']['data-nid']
        );
        $values['attributes']['title'] = $faq_options[$values['attributes']['data-nid']] ?? '';
      }
      if (!empty($values['attributes']['is_rewrite_title']) && !empty($values['attributes']['rewrite_title'])) {
        $values['attributes']['title'] = $values['attributes']['rewrite_title'];
      }
      // There we need to use UserInput because we rewrited the #value in buildForm.
      $user_input = $form_state->getUserInput();
      if (!empty($user_input['attributes']['anchor'])) {
        $values['attributes']['href'] .= '#question-' . $user_input['attributes']['anchor'];
      }
      if (!empty($values['attributes']['open_in_new_window'])) {
        $values['attributes']['target'] = '_blank';
      }
      $form_state->setValues($values);
    }

    return parent::submitForm($form, $form_state);
  }

  /**
   * Refresh questions.
   *
   * @param array $form
   *   The form array to alter, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response with updated form.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function refreshQuestions(
    array &$form,
    FormStateInterface $form_state
  ) {
    $response = new AjaxResponse();
    $form_state->setRebuild(TRUE);
    unset($form['#prefix'], $form['#suffix']);
    $faq_nid = $form_state->getValue(['attributes', 'data-nid']);
    if (!empty($faq_nid)) {
      $form['attributes']['anchor']['#options'] = $this->getFaqQuestionOptions($faq_nid);
    }
    $response->addCommand(new HtmlCommand('#linkit-editor-dialog-form', $form));

    return $response;
  }

  /**
   * Get the faq urls.
   *
   * @return array
   *   The faq urls.
   */
  private function getFaqOptions() {
    $query = $this->nodeStorage->getAggregateQuery();
    $query->condition('type', 'page_faq');
    $query->groupBy('title');
    $query->groupBy('nid');
    $node_values = $query->execute();

    $options = [];
    foreach ($node_values as $node_value) {
      $options[$node_value['nid']] = $node_value['title'];
    }

    return $options;
  }

  /**
   * Get the faq quetions anchors.
   *
   * @return array
   *   The faq quetions anchors.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getFaqQuestionOptions(int $nid) {
    $options = [];

    $faq_node = $this->nodeStorage->load($nid);
    if (!($faq_node instanceof NodeInterface)) {
      return [];
    }

    if ($faq_node->hasField('field_question_responce')) {
      /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field_question_responce */
      $field_question_responce = $faq_node->get('field_question_responce');
      $questions = $field_question_responce->referencedEntities();

      /** @var \Drupal\paragraphs\ParagraphInterface $question */
      foreach ($questions as $question) {
        if ($question->hasField('field_question_answer')) {
          /** @var \Drupal\faqfield\Plugin\Field\FieldType\FaqFieldItem $field_question_answer */
          $field_question_answer = $question->get('field_question_answer')
            ->first();
          $question_string = $field_question_answer->get('question')
            ->getString();
          $options[$question->id()] = $question_string;
        }
      }
    }

    return $options;
  }

}

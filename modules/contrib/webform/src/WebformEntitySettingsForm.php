<?php

namespace Drupal\webform;

use Drupal\webform\Utility\WebformArrayHelper;
use Drupal\webform\Utility\WebformElementHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides a webform to manage settings.
 */
class WebformEntitySettingsForm extends EntityForm {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The message manager.
   *
   * @var \Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager;

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTranslationManagerInterface
   */
  protected $tokenManager;

  /**
   * Constructs a WebformEntitySettingsForm.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\webform\WebformMessageManagerInterface $message_manager
   *   The message manager.
   * @param \Drupal\webform\WebformTokenManagerInterface $token_manager
   *   The token manager.
   */
  public function __construct(AccountInterface $current_user, WebformMessageManagerInterface $message_manager, WebformTokenManagerInterface $token_manager) {
    $this->currentUser = $current_user;
    $this->messageManager = $message_manager;
    $this->tokenManager = $token_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('webform.message_manager'),
      $container->get('webform.token_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->entity;

    $default_settings = $this->config('webform.settings')->get('settings');
    $settings = $webform->getSettings();

    // General.
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];
    $form['general']['id'] = [
      '#type' => 'item',
      '#title' => $this->t('ID'),
      '#markup' => $webform->id(),
      '#value' => $webform->id(),
    ];
    $form['general']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $webform->label(),
      '#required' => TRUE,
      '#id' => 'title',
    ];
    $form['general']['description'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Administrative description'),
      '#default_value' => $webform->get('description'),
    ];
    $form['general']['template'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow this webform to be used as a template.'),
      '#description' => $this->t('If checked, this webform will be available as a template to all users who can create new webforms.'),
      '#access' => $this->moduleHandler->moduleExists('webform_templates'),
      '#default_value' => $webform->isTemplate(),
    ];
    $form['general']['results_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable saving of submissions.'),
      '#description' => $this->t('If saving of submissions is disabled, submission settings, submission limits, purging and the saving of drafts will be disabled. Submissions must be sent via an email or handled using a custom <a href=":href">webform handler</a>.', [':href' => Url::fromRoute('entity.webform.handlers_form', ['webform' => $webform->id()])->toString()]),
      '#return_value' => TRUE,
      '#default_value' => $settings['results_disabled'],
    ];
    // Display warning when disabling the saving of submissions with no
    // handlers.
    if (!$webform->getHandlers(NULL, TRUE, WebformHandlerInterface::RESULTS_PROCESSED)->count()) {
      $this->messageManager->setWebform($webform);
      $form['general']['results_disabled_error'] = [
        '#type' => 'webform_message',
        '#message_type' => 'warning',
        '#message_message' => $this->messageManager->get(WebformMessageManagerInterface::FORM_SAVE_EXCEPTION),
        '#states' => [
          'visible' => [
            ':input[name="results_disabled"]' => ['checked' => TRUE],
            ':input[name="results_disabled_ignore"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $form['general']['results_disabled_ignore'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Ignore disabled results warning'),
        '#description' => $this->t("If checked, all warnings and log messages about 'This webform is currently not saving any submitted data.' will be suppressed."),
        '#return_value' => TRUE,
        '#default_value' => $settings['results_disabled_ignore'],
        '#states' => [
          'visible' => [
            ':input[name="results_disabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Page.
    $form['page'] = [
      '#type' => 'details',
      '#title' => $this->t('URL path settings'),
    ];
    $default_page_submit_path = trim($default_settings['default_page_base_path'], '/') . '/' . str_replace('_', '-', $webform->id());
    $t_args = [
      ':node_href' => Url::fromRoute('node.add', ['node_type' => 'webform'])->toString(),
      ':block_href' => Url::fromRoute('block.admin_display')->toString(),
    ];
    $default_settings['default_page_submit_path'] = $default_page_submit_path;
    $default_settings['default_page_confirm_path'] = $default_page_submit_path . '/confirmation';
    $form['page']['page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to post submission from a dedicated URL.'),
      '#description' => $this->t('If unchecked, this webform must be attached to a <a href=":node_href">node</a> or a <a href=":block_href">block</a> to receive submissions.', $t_args),
      '#default_value' => $settings['page'],
    ];
    if ($this->moduleHandler->moduleExists('path')) {
      $form['page']['page_submit_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Webform URL alias'),
        '#description' => $this->t('Optionally specify an alternative URL by which the webform submit page can be accessed.', $t_args),
        '#default_value' => $settings['page_submit_path'],
        '#states' => [
          'visible' => [
            ':input[name="page"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['page']['page_confirm_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Confirmation page URL alias'),
        '#description' => $this->t('Optionally specify an alternative URL by which the webform confirmation page can be accessed.', $t_args),
        '#default_value' => $settings['page_confirm_path'],
        '#states' => [
          'visible' => [
            ':input[name="page"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Webform.
    $form['webform'] = [
      '#type' => 'details',
      '#title' => $this->t('Webform settings'),
    ];
    $form['webform']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Webform status'),
      '#default_value' => ($webform->get('status') == 1) ? 1 : 0,
      '#description' => $this->t('Closing a webform prevents any further submissions by any users, except submission administrators.'),
      '#options' => [
        1 => $this->t('Open'),
        0 => $this->t('Closed'),
      ],
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="template"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['webform']['form_closed_message']  = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Webform closed message'),
      '#description' => $this->t('A message to be displayed notifying the user that the webform is closed.'),
      '#default_value' => $settings['form_closed_message'],
    ];
    $form['webform']['form_exception_message']  = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Webform exception message'),
      '#description' => $this->t('A message to be displayed if the webform breaks.'),
      '#default_value' => $settings['form_exception_message'],
    ];
    $form['webform']['form_submit'] = [
      '#type' => 'details',
      '#title' => $this->t('Webform submit button'),
    ];
    $form['webform']['form_submit']['form_submit_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webform submit button label'),
      '#size' => 20,
      '#default_value' => $settings['form_submit_label'],
    ];
    $form['webform']['form_submit']['form_submit_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Webform submit button'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.button_classes'),
      '#default_value' => $settings['form_submit_attributes'],
    ];
    $form['webform']['form_prepopulate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow elements to be populated using query string parameters.'),
      '#description' => $this->t("If checked, elements can be populated using query string parameters. For example, appending ?name=John+Smith to a webform's URL would setting an the 'name' element's default value to 'John Smith'."),
      '#return_value' => TRUE,
      '#default_value' => $settings['form_prepopulate'],
    ];
    $form['webform']['form_prepopulate_source_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow source entity to be populated using query string parameters.'),
      '#description' => $this->t("If checked, source entity can be populated using query string parameters. For example, appending ?source_entity_type=user&source_entity_id=1 to a webform's URL would set a submission's 'Submitted to' value to '@user.", ['@user' => User::load(1)->label()]),
      '#return_value' => TRUE,
      '#default_value' => $settings['form_prepopulate_source_entity'],
    ];
    $settings_elements = [
      'form_submit_once' => [
        'title' => $this->t('Prevent duplicate submissions'),
        'all_description' => $this->t('Submit button is disabled immediately after is is clicked form all webforms.'),
        'form_description' => $this->t('If checked, the submit button will be disabled immediately after is is clicked.'),
      ],
      'form_disable_back' => [
        'title' => $this->t('Disable back button for all webforms'),
        'all_description' => $this->t('Back button is disabled for all webforms.'),
        'form_description' => $this->t('If checked, users will not be allowed to navigate back to the webform using the browsers back button.'),
      ],
      'form_unsaved' => [
        'title' => $this->t('Warn users about unsaved changes'),
        'all_description' => $this->t('Unsaved warning is enabled for all webforms.'),
        'form_description' => $this->t('If checked, users will be displayed a warning message when they navigate away from a webform with unsaved changes.'),
      ],
      'form_novalidate' => [
        'title' => $this->t('Disable client-side validation'),
        'all_description' => $this->t('Client-side validation is disabled for all webforms.'),
        'form_description' => $this->t('If checked, the <a href=":href">novalidate</a> attribute, which disables client-side validation, will be added to this webform.', [':href' => 'http://www.w3schools.com/tags/att_form_novalidate.asp']),
      ],
      'form_details_toggle' => [
        'title' => $this->t('Display collapse/expand all details link'),
        'all_description' => $this->t('Expand/collapse all (details) link is automatically added to all webforms.'),
        'form_description' => $this->t('If checked, an expand/collapse all (details) link will be added to this webform when there are two or more details elements available on the webform.'),
      ],
    ];
    foreach ($settings_elements as $settings_key => $setting_element) {
      if ($default_settings['default_' . $settings_key]) {
        $form['webform'][$settings_key . '_disabled'] = [
          '#type' => 'checkbox',
          '#title' => $setting_element['title'],
          '#description' => $setting_element['all_description'],
          '#disabled' => TRUE,
          '#default_value' => TRUE,
        ];
        $form['webform'][$settings_key] = [
          '#type' => 'value',
          '#value' => $settings[$settings_key],
        ];
      }
      else {
        $form['webform'][$settings_key] = [
          '#type' => 'checkbox',
          '#title' => $setting_element['title'],
          '#description' => $setting_element['form_description'],
          '#return_value' => TRUE,
          '#default_value' => $settings[$settings_key],
        ];
      }
    }
    $form['webform']['form_autofocus'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autofocus'),
      '#description' => $this->t('If checked, the first visible and enabled input will be focused when adding new submissions.'),
      '#return_value' => TRUE,
      '#default_value' => $settings['form_autofocus'],
    ];

    // Attributes.
    $elements = $webform->getElementsDecoded();
    $form['attributes'] = [
      '#type' => 'details',
      '#title' => $this->t('Form attributes'),
    ];
    $form['attributes']['attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Webform'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.form_classes'),
      '#default_value' => (isset($elements['#attributes'])) ? $elements['#attributes'] : [],
    ];

    // Wizard.
    $form['wizard'] = [
      '#type' => 'details',
      '#title' => $this->t('Wizard settings'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['wizard']['wizard_progress_bar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show wizard progress bar'),
      '#return_value' => TRUE,
      '#default_value' => $settings['wizard_progress_bar'],
    ];
    $form['wizard']['wizard_progress_pages'] = [
      '#type' => 'checkbox',
      '#return_value' => TRUE,
      '#title' => $this->t('Show wizard progress pages'),
      '#default_value' => $settings['wizard_progress_pages'],
    ];
    $form['wizard']['wizard_progress_percentage'] = [
      '#type' => 'checkbox',
      '#return_value' => TRUE,
      '#title' => $this->t('Show wizard progress percentage'),
      '#default_value' => $settings['wizard_progress_percentage'],
    ];
    $form['wizard']['wizard_prev_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Previous wizard page button'),
      '#description' => $this->t('This is used for the previous page button within a wizard.'),
    ];
    $form['wizard']['wizard_prev_button']['wizard_prev_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Previous wizard page button label'),
      '#size' => 20,
      '#default_value' => $settings['wizard_prev_button_label'],
    ];
    $form['wizard']['wizard_prev_button']['wizard_prev_button_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Previous wizard page button'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.button_classes'),
      '#default_value' => $settings['wizard_prev_button_attributes'],
    ];
    $form['wizard']['wizard_next_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Next wizard page button'),
      '#description' => $this->t('This is used for the next page button within a wizard.'),
    ];
    $form['wizard']['wizard_next_button']['wizard_next_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Next wizard page button label'),
      '#size' => 20,
      '#default_value' => $settings['wizard_next_button_label'],
    ];
    $form['wizard']['wizard_next_button']['wizard_next_button_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Next wizard page button'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.button_classes'),
      '#default_value' => $settings['wizard_next_button_attributes'],
    ];
    $form['wizard']['wizard_complete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include confirmation page in progress'),
      '#return_value' => TRUE,
      '#default_value' => $settings['wizard_complete'],
    ];
    $form['wizard']['wizard_start_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wizard start label'),
      '#size' => 20,
      '#default_value' => $settings['wizard_start_label'],
    ];
    $form['wizard']['wizard_complete_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wizard end label'),
      '#size' => 20,
      '#default_value' => $settings['wizard_complete_label'],
      '#states' => [
        'visible' => [
          ':input[name="wizard_complete"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Preview.
    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview settings'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['preview']['preview'] = [
      '#type' => 'radios',
      '#title' => $this->t('Enable preview page'),
      '#options' => [
        DRUPAL_DISABLED => $this->t('Disabled'),
        DRUPAL_OPTIONAL => $this->t('Optional'),
        DRUPAL_REQUIRED => $this->t('Required'),
      ],
      '#description' => $this->t('Add a page for previewing the webform before submitting.'),
      '#default_value' => $settings['preview'],
    ];
    $form['preview']['settings'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          ':input[name="preview"]' => ['value' => DRUPAL_DISABLED],
        ],
      ],
    ];
    // Preview next button.
    $form['preview']['settings']['preview_next_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview button'),
    ];
    $form['preview']['settings']['preview_next_button']['preview_next_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preview button label'),
      '#description' => $this->t('The text for the button that will proceed to the preview page.'),
      '#size' => 20,
      '#default_value' => $settings['preview_next_button_label'],
    ];
    $form['preview']['settings']['preview_next_button']['preview_next_button_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Preview button'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.button_classes'),
      '#default_value' => $settings['preview_next_button_attributes'],
    ];
    // Preview previous button.
    $form['preview']['settings']['preview_prev_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Previous page button'),
    ];
    $form['preview']['settings']['preview_prev_button']['preview_prev_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Previous page button label'),
      '#description' => $this->t('The text for the button to go backwards from the preview page.'),
      '#size' => 20,
      '#default_value' => $settings['preview_prev_button_label'],
    ];
    $form['preview']['settings']['preview_prev_button']['preview_prev_button_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Previous page button'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.button_classes'),
      '#default_value' => $settings['preview_prev_button_attributes'],
    ];
    $form['preview']['settings']['preview_message'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Preview message'),
      '#description' => $this->t('A message to be displayed on the preview page.'),
      '#default_value' => $settings['preview_message'],
    ];

    // Draft.
    $form['draft'] = [
      '#type' => 'details',
      '#title' => $this->t('Draft settings'),
      '#states' => [
        'visible' => [
          ':input[name="results_disabled"]' => ['checked' => FALSE],
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['draft']['draft'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow your users to save and finish the webform later.'),
      "#description" => $this->t('This option is available only for authenticated users.'),
      '#return_value' => TRUE,
      '#default_value' => $settings['draft'],
    ];
    $form['draft']['settings'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="draft"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['draft']['settings']['draft_auto_save'] = [
      '#type' => 'checkbox',
      '#return_value' => TRUE,
      '#title' => $this->t('Automatically save as draft when paging, previewing, and when there are validation errors.'),
      "#description" => $this->t('Automatically save partial submissions when users click the "Preview" button or when validation errors prevent a webform from being submitted.'),
      '#default_value' => $settings['draft_auto_save'],
    ];
    $form['draft']['settings']['draft_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Draft button'),
    ];
    $form['draft']['settings']['draft_button']['draft_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Draft button label'),
      '#description' => $this->t('The text for the button that will save a draft.'),
      '#size' => 20,
      '#default_value' => $settings['draft_button_label'],
    ];
    $form['draft']['settings']['draft_button']['draft_button_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Draft button'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.button_classes'),
      '#default_value' => $settings['draft_button_attributes'],
    ];
    $form['draft']['settings']['draft_saved_message'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Draft saved message'),
      '#description' => $this->t('Message to be displayed when a draft is saved.'),
      '#default_value' => $settings['draft_saved_message'],
    ];
    $form['draft']['settings']['draft_loaded_message'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Draft loaded message'),
      '#description' => $this->t('Message to be displayed when a draft is loaded.'),
      '#default_value' => $settings['draft_loaded_message'],
    ];

    // Submission.
    $form['submission'] = [
      '#type' => 'details',
      '#title' => $this->t('Submission settings'),
      '#states' => [
        'visible' => [
          ':input[name="results_disabled"]' => ['checked' => FALSE],
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['submission']['form_previous_submissions'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Show the notification about previous submissions'),
      '#description' => $this->t('Show the previous submissions notification that appears when users have previously submitted this form.'),
      '#default_value' => $settings['form_previous_submissions'],
    );
    $form['submission']['form_confidential'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confidential submissions'),
      '#description' => $this->t('Confidential submissions have no recorded IP address and must be submitted while logged out.'),
      '#return_value' => TRUE,
      '#default_value' => $settings['form_confidential'],
    ];
    $form['submission']['form_confidential_message']  = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Webform confidential message'),
      '#description' => $this->t('A message to be displayed when authenticated users try to access a confidential webform.'),
      '#default_value' => $settings['form_confidential_message'],
      '#states' => [
        'visible' => [
          ':input[name="form_confidential"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['submission']['token_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to update a submission using a secure token.'),
      '#description' => $this->t("If checked users will be able to update a submission using the webform's URL appended with the submission's (secure) token.  The URL to update a submission will be available when viewing a submission's information and can be inserted into the an email using the [webform_submission:update-url] token."),
      '#return_value' => TRUE,
      '#default_value' => $settings['token_update'],
    ];
    $form['submission']['next_serial'] = [
      '#type' => 'number',
      '#title' => $this->t('Next submission number'),
      '#description' => $this->t('The value of the next submission number. This is usually 1 when you start and will go up with each webform submission.'),
      '#min' => 1,
      '#default_value' => $webform->getState('next_serial') ?: 1,
    ];

    // Limits.
    $form['limits'] = [
      '#type' => 'details',
      '#title' => $this->t('Submission limits'),
      '#states' => [
        'visible' => [
          ':input[name="results_disabled"]' => ['checked' => FALSE],
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['limits']['limit_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Total submissions limit'),
      '#min' => 1,
      '#default_value' => $settings['limit_total'],
    ];
    $form['limits']['entity_limit_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Total submissions limit per entity'),
      '#min' => 1,
      '#default_value' => $settings['entity_limit_total'],
    ];
    $form['limits']['limit_total_message'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Total submissions limit message'),
      '#min' => 1,
      '#default_value' => $settings['limit_total_message'],
    ];
    $form['limits']['limit_user'] = [
      '#type' => 'number',
      '#title' => $this->t('Per user submission limit'),
      '#min' => 1,
      '#default_value' => $settings['limit_user'],
    ];
    $form['limits']['entity_limit_user'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Per user submission limit per entity'),
      '#default_value' => $settings['entity_limit_user'],
    ];
    $form['limits']['limit_user_message'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Per user submission limit message'),
      '#default_value' => $settings['limit_user_message'],
    ];

    $form['purge'] = [
      '#type' => 'details',
      '#title' => $this->t('Submission purging'),
      '#states' => [
        'visible' => [
          ':input[name="results_disabled"]' => ['checked' => FALSE],
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['purge']['purge'] = [
      '#type' => 'select',
      '#title' => $this->t('Automatically purge'),
      '#options' => [
        WebformSubmissionStorageInterface::PURGE_NONE => $this->t('None'),
        WebformSubmissionStorageInterface::PURGE_DRAFT => $this->t('Draft'),
        WebformSubmissionStorageInterface::PURGE_COMPLETED => $this->t('Completed'),
        WebformSubmissionStorageInterface::PURGE_ALL => $this->t('Draft and completed'),
      ],
      '#default_value' => $settings['purge'],
    ];
    $form['purge']['purge_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to retain submissions'),
      '#min' => 1,
      '#default_value' => $settings['purge_days'],
      '#states' => array(
        'invisible' => array('select[name="purge"]' => array('value' => WebformSubmissionStorageInterface::PURGE_NONE)),
        'optional' => array('select[name="purge"]' => array('value' => WebformSubmissionStorageInterface::PURGE_NONE)),
      ),
      '#field_suffix' => $this->t('days'),
    ];

    // Confirmation.
    $form['confirmation'] = [
      '#type' => 'details',
      '#title' => $this->t('Confirmation settings'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];
    $form['confirmation']['confirmation_type'] = [
      '#title' => $this->t('Confirmation type'),
      '#type' => 'radios',
      '#options' => [
        'page' => $this->t('Page (redirects to new page and displays the confirmation message)'),
        'inline' => $this->t('Inline (reloads the current page and replaces the webform with the confirmation message.)'),
        'message' => $this->t('Message (reloads the current page/form and displays the confirmation message at the top of the page.)'),
        'url' => $this->t('URL (redirects to a custom path or URL)'),
        'url_message' => $this->t('URL with message (redirects to a custom path or URL and displays the confirmation message at the top of the page.)'),
      ],
      '#default_value' => $settings['confirmation_type'],
    ];
    $form['confirmation']['confirmation_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation URL'),
      '#description' => $this->t('URL to redirect the user to upon successful submission.'),
      '#default_value' => $settings['confirmation_url'],
      '#maxlength' => NULL,
      '#states' => [
        'visible' => [
          [':input[name="confirmation_type"]' => ['value' => 'url']],
          'or',
          [':input[name="confirmation_type"]' => ['value' => 'url_message']],
        ],
      ],
    ];
    $form['confirmation']['confirmation_message'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Confirmation message'),
      '#description' => $this->t('Message to be shown upon successful submission.'),
      '#default_value' => $settings['confirmation_message'],
      '#states' => [
        'invisible' => [
          ':input[name="confirmation_type"]' => ['value' => 'url'],
        ],
      ],
    ];
    $form['confirmation']['page'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          [':input[name="confirmation_type"]' => ['value' => 'page']],
          'or',
          [':input[name="confirmation_type"]' => ['value' => 'inline']],
        ],
      ],
    ];
    $form['confirmation']['page']['confirmation_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Confirmation'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.confirmation_classes'),
      '#default_value' => $settings['confirmation_attributes'],
    ];
    $form['confirmation']['page']['confirmation_back'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display back to webform link'),
      '#return_value' => TRUE,
      '#default_value' => $settings['confirmation_back'],
    ];
    $form['confirmation']['page']['back'] = [
      '#type' => 'details',
      '#title' => $this->t('Confirmation back link'),
      '#states' => [
        'visible' => [
          [':input[name="confirmation_back"]' => ['checked' => TRUE]],
        ],
      ],
    ];
    $form['confirmation']['page']['back']['confirmation_back_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation back link label'),
      '#size' => 20,
      '#default_value' => $settings['confirmation_back_label'],
    ];
    $form['confirmation']['page']['back']['confirmation_back_attributes'] = [
      '#type' => 'webform_element_attributes',
      '#title' => $this->t('Confirmation back link'),
      '#classes' => $this->configFactory->get('webform.settings')->get('settings.confirmation_back_classes'),
      '#default_value' => $settings['confirmation_back_attributes'],
    ];
    $form['confirmation']['token_tree_link'] = $this->tokenManager->buildTreeLink();

    // Author.
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Author information'),
      '#access' => $this->currentUser()->hasPermission('administer webform'),
    ];
    $form['author']['uid'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Authored by'),
      '#description' => $this->t("The username of the webform author/owner."),
      '#target_type' => 'user',
      '#settings' => [
        'match_operator' => 'CONTAINS',
      ],
      '#selection_settings' => [
        'include_anonymous' => TRUE,
      ],
      '#default_value' => $webform->getOwner(),
    ];

    // Custom.
    $properties = WebformElementHelper::getProperties($webform->getElementsDecoded());
    // Set default properties.
    $properties += [
      '#method' => '',
      '#action' => '',
    ];
    $form['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom settings'),
      '#open' => array_filter($properties) ? TRUE : FALSE,
      '#access' => !$this->moduleHandler->moduleExists('webform_ui') || $this->currentUser()->hasPermission('edit webform source'),
    ];
    $form['custom']['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#description' => $this->t('The HTTP method with which the form will be submitted.') . '<br/>' .
        '<em>' . $this->t('Selecting a custom POST or GET method will automatically disable wizards, previews, drafts, submissions, limits, purging, and confirmations.') . '</em>',
      '#options' => [
        '' => $this->t('POST (Default)'),
        'post' => $this->t('POST (Custom)'),
        'get' => $this->t('GET (Custom)'),
      ],
      '#default_value' => $properties['#method'],
    ];
    $form['custom']['method_message'] = [
      '#type' => 'webform_message',
      '#message_type' => 'warning',
      '#message_message' => $this->t("Please make sure this webform's action URL or path is setup to handle the webform's submission."),
      '#states' => [
        'invisible' => [
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
    ];

    $form['custom']['action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action'),
      '#description' => $this->t('The URL or path to which the webform will be submitted.'),
      '#states' => [
        'invisible' => [
          ':input[name="method"]' => ['value' => ''],
        ],
        'optional' => [
          ':input[name="method"]' => ['value' => ''],
        ],
      ],
      '#default_value' => $properties['#action'],
    ];
    // Unset properties that are webform settings.
    unset(
      $properties['#method'],
      $properties['#action'],
      $properties['#novalidate'],
      $properties['#attributes']
    );
    $form['custom']['custom'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Custom properties'),
      '#description' =>
        $this->t('Properties do not have to prepended with a hash (#) character, the hash character will be automatically added upon submission.') .
        '<br/>' .
        $this->t('These properties and callbacks are not allowed: @properties', ['@properties' => WebformArrayHelper::toString(WebformArrayHelper::addPrefix(WebformElementHelper::$ignoredProperties))]),
      '#default_value' => WebformArrayHelper::removePrefix($properties),
    ];

    $this->appendDefaultValueToElementDescriptions($form, $default_settings);

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getEntity();

    // Set custom properties, class, and style.
    $elements = $webform->getElementsDecoded();
    $elements = WebformElementHelper::removeProperties($elements);
    $properties = [];
    if (!empty($values['method'])) {
      $properties['#method'] = $values['method'];
    }
    if (!empty($values['action'])) {
      $properties['#action'] = $values['action'];
    }
    if (!empty($values['custom'])) {
      $properties += WebformArrayHelper::addPrefix($values['custom']);
    }
    if (!empty($values['attributes'])) {
      $properties['#attributes'] = $values['attributes'];
    }
    $elements = $properties + $elements;
    $webform->setElements($elements);

    // Remove custom properties and attributes.
    unset(
      $values['method'],
      $values['action'],
      $values['attributes'],
      $values['custom']
    );

    /** @var \Drupal\webform\WebformSubmissionStorageInterface $submission_storage */
    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    // Set next serial number.
    $next_serial = (int) $values['next_serial'];
    $max_serial = $submission_storage->getMaxSerial($webform);
    if ($next_serial < $max_serial) {
      drupal_set_message($this->t('The next submission number was increased to @min to make it higher than existing submissions.', ['@min' => $max_serial]));
      $next_serial = $max_serial;
    }
    $webform->setState('next_serial', $next_serial);

    // Remove main properties.
    unset(
      $values['id'],
      $values['title'],
      $values['description'],
      $values['template'],
      $values['status'],
      $values['uid'],
      $values['next_serial']
    );

    // Remove disabled properties.
    unset(
      $values['form_novalidate_disabled'],
      $values['form_unsaved_disabled'],
      $values['form_details_toggle_disabled']
    );

    // Set settings.
    $webform->setSettings($values);

    // Save the webform.
    $webform->save();

    $this->logger('webform')->notice('Webform settings @label saved.', ['@label' => $webform->label()]);
    drupal_set_message($this->t('Webform settings %label saved.', ['%label' => $webform->label()]));
  }

  /**
   * Append default value to an element's description.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $default_settings
   *   An associative array container default webform settings.
   */
  protected function appendDefaultValueToElementDescriptions(array &$form, array $default_settings) {
    foreach ($form as $key => &$element) {
      // Skip if not a FAPI element.
      if (Element::property($key) || !is_array($element)) {
        continue;
      }

      if (isset($element['#type']) && !empty($default_settings["default_$key"]) && empty($element['#disabled'])) {
        if (!isset($element['#description'])) {
          $element['#description'] = '';
        }
        $element['#description'] .= ($element['#description'] ? '<br/>' : '');
        $element['#description'] .= $this->t('Defaults to: %value', ['%value' => $default_settings["default_$key"]]);
      }

      $this->appendDefaultValueToElementDescriptions($element, $default_settings);
    }
  }

}

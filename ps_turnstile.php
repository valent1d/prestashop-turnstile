<?php
/* 
   ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
   ~    ____ _                 _  __ _                  ~
   ~   / ___| | ___  _   _  __| |/ _| | __ _ _ __ ___   ~
   ~  | |   | |/ _ \| | | |/ _` | |_| |/ _` | '__/ _ \  ~
   ~  | |___| | (_) | |_| | (_| |  _| | (_| | | |  __/  ~
   ~   \____|_|\___/ \__,_|\__,_|_| |_|\__,_|_|  \___|  ~
   ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~         

   Cloudflare Turnstile CAPTCHA 
   -------------------------------------------------------

   Module Prestashop Cloudflare Turnstile 
   By Digibleo

   Protect your Prestashop forms with the best CAPTCHA.

   (c) 2024 Digibleo. All rights reserved.
   Unauthorized copying, modification, or distribution of this code is strictly prohibited.
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Turnstile extends Module
{
    public function __construct()
    {
        $this->name = 'ps_turnstile';
        $this->tab = 'front_office_features';
        $this->version = '1.0.21';
        $this->author = 'Digibleo';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Cloudflare Turnstile by Digibleo');
        $this->description = $this->l('Intégration du CAPTCHA de Cloudflare Turnstile dans le formulaire de contact, d\'inscription et de connexion.');
    }

    public function install()
{
    return parent::install() &&
        $this->registerHook('header') &&
        $this->registerHook('actionFrontControllerAfterInit') &&
        $this->registerHook('actionCustomerAccountAdd');
}

public function hookHeader($params)
{
    $controller = $this->context->controller->php_self;
    $allowed_controllers = ['contact', 'contact-us', 'authentication', 'registration'];
    
    if (in_array($controller, $allowed_controllers) || $this->context->controller instanceof RegistrationController) {
        $this->context->controller->addJS($this->_path . 'views/js/ps_turnstile.js');
        Media::addJsDef([
            'prestashop' => [
                'turnstileSiteKey' => Configuration::get('TURNSTILE_SITE_KEY'),
            ]
        ]);
    }
}

public function hookActionFrontControllerAfterInit($params)
{
    $controller = $this->context->controller->php_self;
    $allowed_controllers = ['contact', 'contact-us', 'authentication', 'registration'];
    
    if (in_array($controller, $allowed_controllers) || $this->context->controller instanceof RegistrationController) {
        $form_action = '';
        if ($controller == 'contact' || $controller == 'contact-us') {
            $form_action = 'submitMessage';
        } elseif (($controller == 'authentication' && Tools::isSubmit('submitCreate')) || $controller == 'registration') {
            $form_action = 'submitCreate';
        }

        if ($form_action && Tools::isSubmit($form_action)) {
            $this->validateTurnstile($form_action);
        }
    }
}

public function hookActionCustomerAccountAdd($params)
{
    // Cette méthode sera appelée lors de la création d'un compte client
    // Vous pouvez ajouter ici une validation supplémentaire si nécessaire
}

private function validateTurnstile($redirect_action)
{
    $turnstile_response = Tools::getValue('cf-turnstile-response');
    $secret = Configuration::get('TURNSTILE_SECRET_KEY');

    // Vérifier si Turnstile est chargé (JavaScript activé)
    $is_turnstile_loaded = Tools::getValue('turnstile_loaded', false);

    if (!$is_turnstile_loaded) {
        $this->context->controller->errors[] = $this->l('JavaScript est requis pour la vérification de sécurité. Veuillez l\'activer et réessayer.');
        $this->redirectWithNotifications($redirect_action);
    }

    if (!$turnstile_response) {
        $this->context->controller->errors[] = $this->l('Le CAPTCHA n\'a pas été rempli. Merci de réessayer.');
        $this->redirectWithNotifications($redirect_action);
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $turnstile_response,
        'remoteip' => Tools::getRemoteAddr(),
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response);

    if (!$result->success) {
        $this->context->controller->errors[] = $this->l('La vérification du CAPTCHA a échoué. Merci de réessayer.');
        $this->redirectWithNotifications($redirect_action);
    }
}

private function redirectWithNotifications($action)
{
    if ($action == 'submitMessage') {
        $this->context->controller->redirectWithNotifications($this->context->link->getPageLink('contact'));
    } elseif ($action == 'submitCreate') {
        $this->context->controller->redirectWithNotifications($this->context->link->getPageLink('authentication', true, null, ['create_account' => '1']));
    }
    exit;
}

    public function getContent()
    {
        $html = '<div class="alert alert-info">';
        $html .= '<h4>' . $this->l('Comment créer un site dans Cloudflare Turnstile') . '</h4>';
        $html .= '<p>' . $this->l('Pour créer un site dans Cloudflare Turnstile, suivez ces étapes :') . '</p>';
        $html .= '<ol>';
        $html .= '<li>' . $this->l('Allez sur le site web de Cloudflare.') . '</li>';
        $html .= '<li>' . $this->l('Connectez-vous à votre compte ou créez-en un nouveau si vous n\'avez pas encore de compte.') . '</li>';
        $html .= '<li>' . $this->l('Naviguez vers la section "Turnstile" dans la barre latérale gauche.') . '</li>';
        $html .= '<li>' . $this->l('Cliquez sur "Ajouter un nouveau site" et remplissez les détails nécessaires.') . '</li>';
        $html .= '<li>' . $this->l('Indiquez les clé de site et clé secrète obtenues ci-dessous') . '</li>';
        $html .= '</ol>';
        $html .= '<p><a href="https://dash.cloudflare.com/" target="_blank">' . $this->l('Accéder au Dashboard Cloudflare.') . '</a></p>';
        $html .= '</div>';

        if (Tools::isSubmit('submitPsTurnstile')) {
            Configuration::updateValue('TURNSTILE_SITE_KEY', Tools::getValue('TURNSTILE_SITE_KEY'));
            Configuration::updateValue('TURNSTILE_SECRET_KEY', Tools::getValue('TURNSTILE_SECRET_KEY'));
            $this->context->controller->confirmations[] = $this->l('Paramètres mis à jour');
        }

        $html .= $this->renderForm();
        $html .= '<div style="margin-top:20px; text-align:center;">';
        $html .= '<p>&copy; ' . date('Y') . ' Digibleo</p>';
        $html .= '</div>';

        return $html;
    }

    private function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Paramètrage de Cloudflare Turnstile'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Clé du site'),
                        'name' => 'TURNSTILE_SITE_KEY',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Clé secrète'),
                        'name' => 'TURNSTILE_SECRET_KEY',
                        'size' => 20,
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Sauvegarder'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->submit_action = 'submitPsTurnstile';
        $helper->fields_value['TURNSTILE_SITE_KEY'] = Configuration::get('TURNSTILE_SITE_KEY');
        $helper->fields_value['TURNSTILE_SECRET_KEY'] = Configuration::get('TURNSTILE_SECRET_KEY');

        return $helper->generateForm([$fields_form]);
    }
}
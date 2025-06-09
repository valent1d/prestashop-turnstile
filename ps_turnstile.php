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
   By VLTN

   Protect your Prestashop forms with the best CAPTCHA.

   (c) 2024-2025 VLTN. All rights reserved.
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
        $this->version = '2.0.0';
        $this->author = 'VLTN';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Cloudflare Turnstile by VLTN');
        $this->description = $this->l('Intégration du CAPTCHA de Cloudflare Turnstile dans le formulaire de contact, d\'inscription et de connexion.');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('actionFrontControllerAfterInit') &&
            $this->registerHook('actionCustomerAccountAdd') &&
            Configuration::updateValue('TURNSTILE_SITE_KEY', '') &&
            Configuration::updateValue('TURNSTILE_SECRET_KEY', '') &&
            $this->registerHook('displayCustomerAccountForm') &&
            Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'turnstile_attempts` (
                `id_attempt` int(11) NOT NULL AUTO_INCREMENT,
                `ip_address` varchar(45) NOT NULL,
                `attempt_time` datetime NOT NULL,
                `form_type` varchar(50) NOT NULL,
                `success` tinyint(1) NOT NULL DEFAULT 0,
                `error_message` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id_attempt`),
                KEY `ip_address` (`ip_address`),
                KEY `attempt_time` (`attempt_time`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;');
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('TURNSTILE_SITE_KEY') &&
            Configuration::deleteByName('TURNSTILE_SECRET_KEY') &&
            Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'turnstile_attempts`');
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
                    'turnstileTheme' => 'light',
                    'turnstileLanguage' => $this->context->language->iso_code
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

    public function hookDisplayCustomerAccountForm($params)
    {
        $token = Tools::getToken(false);
        $this->context->smarty->assign([
            'turnstile_csrf_token' => $token
        ]);
        return $this->display(__FILE__, 'csrf_token.tpl');
    }

    private function checkRateLimit(string $form_type): bool
    {
        $ip_address = Tools::getRemoteAddr();
        $time_window = 3600; // 1 heure
        $max_attempts = 10; // Maximum 10 tentatives par heure

        // Nettoyage des anciennes tentatives
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'turnstile_attempts` 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL '.$time_window.' SECOND)');

        // Comptage des tentatives récentes
        $attempts = Db::getInstance()->getValue('SELECT COUNT(*) 
            FROM `'._DB_PREFIX_.'turnstile_attempts` 
            WHERE ip_address = "'.pSQL($ip_address).'" 
            AND form_type = "'.pSQL($form_type).'" 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL '.$time_window.' SECOND)');

        return $attempts < $max_attempts;
    }

    private function logAttempt(string $form_type, bool $success, ?string $error_message = null): void
    {
        Db::getInstance()->insert('turnstile_attempts', [
            'ip_address' => pSQL(Tools::getRemoteAddr()),
            'attempt_time' => date('Y-m-d H:i:s'),
            'form_type' => pSQL($form_type),
            'success' => (int)$success,
            'error_message' => $error_message ? pSQL($error_message) : null
        ]);
    }

    private function getErrorMessage(string $error_code): string
    {
        $error_messages = [
            'invalid-input-response' => 'La réponse du CAPTCHA est invalide. Veuillez réessayer.',
            'invalid-input-secret' => 'Erreur de configuration du serveur. Veuillez contacter l\'administrateur.',
            'missing-input-response' => 'Veuillez compléter le CAPTCHA avant de soumettre le formulaire.',
            'missing-input-secret' => 'Erreur de configuration du serveur. Veuillez contacter l\'administrateur.',
            'timeout-or-duplicate' => 'Le CAPTCHA a expiré. Veuillez réessayer.',
            'internal-error' => 'Une erreur interne est survenue. Veuillez réessayer dans quelques instants.',
            'rate-limit-exceeded' => 'Trop de tentatives. Veuillez réessayer dans une heure.',
            'csrf-invalid' => 'Erreur de sécurité : Session invalide. Veuillez rafraîchir la page.',
            'javascript-disabled' => 'JavaScript est requis pour la vérification de sécurité. Veuillez l\'activer et réessayer.',
            'turnstile-not-configured' => 'Le module de sécurité n\'est pas correctement configuré. Veuillez contacter l\'administrateur.'
        ];

        return $error_messages[$error_code] ?? 'Une erreur inattendue est survenue. Veuillez réessayer.';
    }

    private function validateTurnstile(string $redirect_action): void
    {
        try {
            // Vérification du rate limiting
            if (!$this->checkRateLimit($redirect_action)) {
                $this->logAttempt($redirect_action, false, 'rate-limit-exceeded');
                throw new PrestaShopException($this->getErrorMessage('rate-limit-exceeded'));
            }

            // Vérification du token CSRF
            if (!Tools::getToken(false) || Tools::getToken(false) !== Tools::getValue('turnstile_csrf_token')) {
                $this->logAttempt($redirect_action, false, 'csrf-invalid');
                throw new PrestaShopException($this->getErrorMessage('csrf-invalid'));
            }

            if (!Configuration::get('TURNSTILE_SITE_KEY') || !Configuration::get('TURNSTILE_SECRET_KEY')) {
                $this->logAttempt($redirect_action, false, 'turnstile-not-configured');
                throw new PrestaShopException($this->getErrorMessage('turnstile-not-configured'));
            }

            $turnstile_response = $this->context->request->get('cf-turnstile-response');
            $secret = Configuration::get('TURNSTILE_SECRET_KEY');

            // Vérifier si Turnstile est chargé (JavaScript activé)
            $is_turnstile_loaded = $this->context->request->get('turnstile_loaded', false);

            if (!$is_turnstile_loaded) {
                $this->logAttempt($redirect_action, false, 'javascript-disabled');
                throw new PrestaShopException($this->getErrorMessage('javascript-disabled'));
            }

            if (!$turnstile_response) {
                $this->logAttempt($redirect_action, false, 'missing-input-response');
                throw new PrestaShopException($this->getErrorMessage('missing-input-response'));
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
                    'timeout' => 5,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                $this->logAttempt($redirect_action, false, 'internal-error');
                throw new PrestaShopException($this->getErrorMessage('internal-error'));
            }

            $result = json_decode($response);

            if (!$result || !isset($result->success)) {
                $this->logAttempt($redirect_action, false, 'internal-error');
                throw new PrestaShopException($this->getErrorMessage('internal-error'));
            }

            if (!$result->success) {
                $error_code = isset($result->{'error-codes'}) ? $result->{'error-codes'}[0] : 'internal-error';
                $this->logAttempt($redirect_action, false, $error_code);
                throw new PrestaShopException($this->getErrorMessage($error_code));
            }

            // Log de la tentative réussie
            $this->logAttempt($redirect_action, true);

        } catch (PrestaShopException $e) {
            $this->context->controller->errors[] = $e->getMessage();
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
        $html = '';
        
        if (Tools::isSubmit('submitPsTurnstile')) {
            $site_key = Tools::getValue('TURNSTILE_SITE_KEY');
            $secret_key = Tools::getValue('TURNSTILE_SECRET_KEY');

            if (empty($site_key) || empty($secret_key)) {
                $this->context->controller->errors[] = $this->l('Les clés Turnstile sont requises.');
            } else {
                Configuration::updateValue('TURNSTILE_SITE_KEY', $site_key);
                Configuration::updateValue('TURNSTILE_SECRET_KEY', $secret_key);
                $this->context->controller->confirmations[] = $this->l('Paramètres mis à jour');
            }
        }

        $html .= $this->renderHelp();
        $html .= $this->renderForm();
        $html .= $this->renderFooter();

        return $html;
    }

    private function renderHelp()
    {
        return '<div class="alert alert-info">' .
            '<h4>' . $this->l('Comment créer un site dans Cloudflare Turnstile') . '</h4>' .
            '<p>' . $this->l('Pour créer un site dans Cloudflare Turnstile, suivez ces étapes :') . '</p>' .
            '<ol>' .
            '<li>' . $this->l('Allez sur le site web de Cloudflare.') . '</li>' .
            '<li>' . $this->l('Connectez-vous à votre compte ou créez-en un nouveau si vous n\'avez pas encore de compte.') . '</li>' .
            '<li>' . $this->l('Naviguez vers la section "Turnstile" dans la barre latérale gauche.') . '</li>' .
            '<li>' . $this->l('Cliquez sur "Ajouter un nouveau site" et remplissez les détails nécessaires.') . '</li>' .
            '<li>' . $this->l('Indiquez les clé de site et clé secrète obtenues ci-dessous') . '</li>' .
            '</ol>' .
            '<p><a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">' . $this->l('Accéder au Dashboard Cloudflare.') . '</a></p>' .
            '</div>';
    }

    private function renderFooter()
    {
        return '<div style="margin-top:20px; text-align:center;">' .
            '<p>&copy; ' . date('Y') . ' VLTN</p>' .
            '</div>';
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
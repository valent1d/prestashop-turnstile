# Changelog

Toutes les modifications notables apportées à ce projet seront documentées dans ce fichier.

## [2.0.1] - 2025-06-27

### Modifié
- Suppression de toute la logique CSRF spécifique à Turnstile qui pouvait créer des erreurs sur certaines installations Prestashop (le module s'appuie désormais uniquement sur la protection native de PrestaShop).
- Nettoyage du code : suppression des hooks et templates inutiles (`hookActionCustomerAccountAdd`, `hookDisplayCustomerAccountForm`, `views/templates/hook/csrf_token.tpl`).
- Correction de la compatibilité avec PrestaShop 1.7 et 8 pour la récupération des valeurs du formulaire (`Tools::getValue` au lieu de `$this->context->request->get`).
- Correction de la détection de la soumission du formulaire d'inscription pour éviter les rechargements intempestifs.
- Amélioration de la redirection après inscription pour supporter tous les contrôleurs (`registration`, `inscription`, `authentication`).
- Ajout du champ caché `turnstile_loaded` côté JS pour garantir la détection du JS actif lors de la validation serveur.
- Nettoyage général et simplification du module.

### Corrigé
- Résolution des erreurs 500 lors de l'inscription (propriété `request` inexistante).
- Résolution du problème d'affichage du captcha sur toutes les variantes de pages de connexion/inscription.
- Résolution du blocage de l'inscription dû à la vérification CSRF superflue. 

## [2.0.0] - 2025-06-10

### Ajouté
- Refactorisation complète du code pour une meilleure maintenabilité
- Implémentation de la vérification CSRF pour une sécurité renforcée
- Système de rate limiting pour prévenir les attaques par force brute
- Journalisation des tentatives de soumission
- Messages d'erreur améliorés et plus détaillés
- Support multilingue des messages d'erreur
- Animation de secousse pour les messages d'erreur
- Indicateur de chargement pendant la vérification
- Timeout automatique après 5 secondes
- Style amélioré des messages d'erreur
- Icônes pour une meilleure visibilité des erreurs

### Modifié
- Amélioration de la gestion des erreurs
- Optimisation des performances
- Amélioration de l'expérience utilisateur
- Refonte du système de validation

### Sécurité
- Ajout de la protection CSRF
- Implémentation du rate limiting
- Amélioration de la validation des entrées
- Journalisation des tentatives pour le débogage

## [1.0.23] - 2025-04-14

### Ajouté
- Compatibilité avec PrestaShop 8.x
- Support des nouvelles fonctionnalités de PrestaShop 8

### Corrigé
- Bugs mineurs de compatibilité
- Problèmes d'affichage sur certains thèmes
- Corrections de sécurité mineures

## [1.0.22] - 2024-08-23

### Corrigé
- Correction des problèmes de validation
- Résolution des bugs d'affichage
- Correction des erreurs de compatibilité
- Amélioration de la stabilité générale

## [1.0.0] - 2024-08-23

### Ajouté
- Version initiale du module
- Intégration de Cloudflare Turnstile
- Support des formulaires de contact
- Support du formulaire d'inscription
- Support du formulaire de connexion
- Interface d'administration pour la configuration
- Documentation d'installation et d'utilisation
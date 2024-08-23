console.log("Turnstile JS is loaded.");
document.addEventListener("DOMContentLoaded", function () {
    const turnstilePaths = ['/contact-us', '/nous-contacter', '/connexion', '/login', '/inscription', '/register', '/registration'];
    
    if (turnstilePaths.some(path => window.location.pathname.includes(path))) {
        const forms = {
            contact: document.querySelector('section.contact-form form'),
            register: document.querySelector('#customer-form, form#registration-form'), // Ajout d'un sélecteur alternatif
            login: document.querySelector('#login-form')
        };
        
        Object.entries(forms).forEach(([formType, form]) => {
            if (form) {
                addTurnstileToForm(form, formType);
            }
        });
    }
});

function addTurnstileToForm(form, formType) {
    var turnstileDiv = document.createElement('div');
    turnstileDiv.className = 'cf-turnstile';
    turnstileDiv.setAttribute('data-sitekey', prestashop.turnstileSiteKey);
    turnstileDiv.setAttribute('data-theme', 'light');
    
    // Styles communs pour centrer le widget
    turnstileDiv.style.display = 'flex';
    turnstileDiv.style.justifyContent = 'center';
    turnstileDiv.style.alignItems = 'center';
    turnstileDiv.style.marginTop = '20px';
    turnstileDiv.style.marginBottom = '20px';

    if (formType === 'login') {
        // Pour le formulaire de connexion, on ajoute des styles spécifiques
        turnstileDiv.style.display = 'flex';
        turnstileDiv.style.justifyContent = 'center';
        turnstileDiv.style.marginTop = '20px';
        
        // Trouver le champ de mot de passe
        var passwordField = form.querySelector('input[type="password"]');
        if (passwordField) {
            var fieldContainer = passwordField.closest('.form-group');
            if (fieldContainer) {
                fieldContainer.parentNode.insertBefore(turnstileDiv, fieldContainer.nextSibling);
            } else {
                passwordField.parentNode.insertBefore(turnstileDiv, passwordField.nextSibling);
            }
        } else {
            form.appendChild(turnstileDiv);
        }
    } else if (formType === 'register') {
        // Pour le formulaire d'inscription, on ajoute le widget avant le bouton de soumission
        var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            // Créer un conteneur pour le widget
            var widgetContainer = document.createElement('div');
            widgetContainer.style.width = '100%';
            widgetContainer.style.display = 'flex';
            turnstileDiv.style.marginTop = '0px';
            widgetContainer.style.justifyContent = 'center';
            widgetContainer.appendChild(turnstileDiv);
            
            submitButton.parentNode.insertBefore(widgetContainer, submitButton);
        } else {
            form.appendChild(turnstileDiv);
        }
    } else {
        // Pour les autres formulaires, on garde le comportement original
        var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            submitButton.parentNode.insertBefore(turnstileDiv, submitButton);
        } else {
            form.appendChild(turnstileDiv);
        }
    }
    
    // Ajouter un champ caché pour indiquer que Turnstile est chargé
    var turnstileLoadedInput = document.createElement('input');
    turnstileLoadedInput.type = 'hidden';
    turnstileLoadedInput.name = 'turnstile_loaded';
    turnstileLoadedInput.value = '1';
    form.appendChild(turnstileLoadedInput);

    if (!document.querySelector('script[src="https://challenges.cloudflare.com/turnstile/v0/api.js"]')) {
        var script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    }

    // Prevent form submission if CAPTCHA is not solved
    form.addEventListener('submit', function(event) {
        var turnstileResponse = form.querySelector('[name="cf-turnstile-response"]');
        if (!turnstileResponse || !turnstileResponse.value) {
            event.preventDefault();
            alert('Veuillez résoudre le CAPTCHA avant de soumettre le formulaire.');
        }
    });
}
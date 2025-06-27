/**
 * Cloudflare Turnstile Integration for PrestaShop
 * @author VLTN
 * @version 2.0.1
 */

console.log("Turnstile JS is loaded.");

document.addEventListener("DOMContentLoaded", () => {
    const turnstilePaths = ['/contact-us', '/nous-contacter', '/connexion', '/login', '/authentification', '/inscription', '/register', '/registration'];
    
    if (turnstilePaths.some(path => window.location.pathname.includes(path))) {
        const forms = {
            contact: document.querySelector('section.contact-form form'),
            register: document.querySelector('#customer-form, form#registration-form'),
            login: document.querySelector('#login-form')
        };
        
        Object.entries(forms).forEach(([formType, form]) => {
            if (form) {
                addTurnstileToForm(form, formType);
            }
        });
    }
});

/**
 * Ajoute le widget Turnstile à un formulaire
 * @param {HTMLFormElement} form - Le formulaire cible
 * @param {string} formType - Le type de formulaire (contact, register, login)
 */
const addTurnstileToForm = (form, formType) => {
    const turnstileDiv = document.createElement('div');
    turnstileDiv.className = 'cf-turnstile';
    turnstileDiv.setAttribute('data-sitekey', prestashop.turnstileSiteKey);
    turnstileDiv.setAttribute('data-theme', 'light');
    
    // Styles communs
    Object.assign(turnstileDiv.style, {
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: '20px',
        marginBottom: '20px'
    });

    // Positionnement spécifique selon le type de formulaire
    const positionTurnstile = () => {
        switch(formType) {
            case 'login':
                const passwordField = form.querySelector('input[type="password"]');
                if (passwordField) {
                    const fieldContainer = passwordField.closest('.form-group');
                    fieldContainer?.parentNode.insertBefore(turnstileDiv, fieldContainer.nextSibling);
                } else {
                    form.appendChild(turnstileDiv);
                }
                break;

            case 'register':
                const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitButton) {
                    const widgetContainer = document.createElement('div');
                    Object.assign(widgetContainer.style, {
                        width: '100%',
                        display: 'flex',
                        justifyContent: 'center'
                    });
                    turnstileDiv.style.marginTop = '0px';
                    widgetContainer.appendChild(turnstileDiv);
                    submitButton.parentNode.insertBefore(widgetContainer, submitButton);
                } else {
                    form.appendChild(turnstileDiv);
                }
                break;

            default:
                const defaultSubmitButton = form.querySelector('button[type="submit"], input[type="submit"]');
                if (defaultSubmitButton) {
                    defaultSubmitButton.parentNode.insertBefore(turnstileDiv, defaultSubmitButton);
                } else {
                    form.appendChild(turnstileDiv);
                }
        }
    };

    positionTurnstile();
    
    // Ajout du champ caché pour indiquer que JS est actif
    const turnstileLoadedInput = document.createElement('input');
    turnstileLoadedInput.type = 'hidden';
    turnstileLoadedInput.name = 'turnstile_loaded';
    turnstileLoadedInput.value = '1';
    form.appendChild(turnstileLoadedInput);
    
    // Chargement du script Turnstile avec gestion du cache
    if (!document.querySelector('script[src="https://challenges.cloudflare.com/turnstile/v0/api.js"]')) {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        script.async = true;
        script.defer = true;
        script.onerror = () => {
            console.error('Erreur lors du chargement du script Turnstile');
            alert('Erreur lors du chargement de la vérification de sécurité. Veuillez rafraîchir la page.');
        };
        document.head.appendChild(script);
    }

    // Validation du formulaire
    form.addEventListener('submit', (event) => {
        const turnstileResponse = form.querySelector('[name="cf-turnstile-response"]');
        
        if (!turnstileResponse?.value) {
            event.preventDefault();
            showError(form, 'Veuillez compléter le CAPTCHA avant de soumettre le formulaire.');
            return;
        }

        // Ajouter un indicateur de chargement
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Vérification en cours...';
            
            // Restaurer le bouton après 5 secondes si la requête échoue
            setTimeout(() => {
                if (submitButton.disabled) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    showError(form, 'Le temps de réponse est trop long. Veuillez réessayer.');
                }
            }, 5000);
        }
    });
};

/**
 * Affiche un message d'erreur dans le formulaire
 * @param {HTMLFormElement} form - Le formulaire
 * @param {string} message - Le message d'erreur
 */
function showError(form, message) {
    const errorMessage = document.createElement('div');
    errorMessage.className = 'alert alert-danger';
    errorMessage.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="material-icons mr-2">error_outline</i>
            <span>${message}</span>
        </div>
    `;
    
    form.insertBefore(errorMessage, form.firstChild);
    errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Ajouter une animation de secousse au formulaire
    form.classList.add('shake');
    setTimeout(() => form.classList.remove('shake'), 500);
}

// Ajouter le style CSS pour l'animation de secousse
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    .shake {
        animation: shake 0.5s ease-in-out;
    }
    .alert-danger {
        margin-bottom: 1rem;
        padding: 0.75rem 1.25rem;
        border: 1px solid #f5c6cb;
        border-radius: 0.25rem;
        background-color: #f8d7da;
        color: #721c24;
    }
    .alert-danger i {
        margin-right: 0.5rem;
    }
`;
document.head.appendChild(style);
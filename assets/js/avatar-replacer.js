/**
 * Avatar Replacer for Better Messages
 * Zamienia awatary użytkowników na loga firm w Better Messages
 */
class RopAvatarReplacer {
    constructor() {
        this.companyLogos = {};
        this.init();
    }

    init() {
        // Poczekaj na załadowanie DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.start());
        } else {
            this.start();
        }
    }

    start() {
        // Obserwuj zmiany w DOM dla dynamicznie ładowanych wiadomości
        this.observeMessages();
        
        // Zamień istniejące awatary
        this.replaceExistingAvatars();
        
        // Nasłuchuj na AJAX requesty Better Messages
        this.interceptAjaxRequests();
    }

    observeMessages() {
        // Obserwuj kontener Better Messages
        const containers = [
            '.better-messages-list',
            '.bm-messages-wrap',
            '.better-messages-conversation',
            '.bm-conversation-wrap',
            '#better-messages-container',
            '.better-messages'
        ];

        containers.forEach(selector => {
            const container = document.querySelector(selector);
            if (container) {
                this.observeContainer(container);
            }
        });
    }

    observeContainer(container) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        this.replaceAvatarsInElement(node);
                    }
                });
            });
        });

        observer.observe(container, {
            childList: true,
            subtree: true
        });
    }

    replaceExistingAvatars() {
        // Znajdź wszystkie awatary w Better Messages
        const avatarSelectors = [
            '.bm-pic img',
            '.better-messages-avatar img',
            '.bm-avatar img',
            '.avatar.bbpm-avatar',
            '.bm-pic .avatar',
            '.better-messages-list .avatar'
        ];

        avatarSelectors.forEach(selector => {
            const avatars = document.querySelectorAll(selector);
            avatars.forEach(avatar => this.replaceAvatar(avatar));
        });
    }

    replaceAvatarsInElement(element) {
        // Sprawdź czy element sam jest awatarem
        if (this.isAvatarElement(element)) {
            this.replaceAvatar(element);
        }
        
        // Znajdź awatary w elemencie
        const avatarSelectors = [
            '.bm-pic img',
            '.better-messages-avatar img',
            '.bm-avatar img',
            '.avatar.bbpm-avatar',
            '.bm-pic .avatar'
        ];

        avatarSelectors.forEach(selector => {
            const avatars = element.querySelectorAll(selector);
            avatars.forEach(avatar => this.replaceAvatar(avatar));
        });
    }

    isAvatarElement(element) {
        return element.matches && (
            element.matches('.avatar') ||
            element.matches('.bm-pic img') ||
            element.matches('.better-messages-avatar img') ||
            element.matches('.bm-avatar img')
        );
    }

    replaceAvatar(avatarElement) {
        if (!avatarElement || avatarElement.dataset.ropProcessed) {
            return;
        }

        // Oznacz jako przetworzony
        avatarElement.dataset.ropProcessed = 'true';

        // Wyciągnij user ID z różnych miejsc
        const userId = this.extractUserId(avatarElement);
        
        if (userId) {
            this.getCompanyLogo(userId).then(logoUrl => {
                if (logoUrl) {
                    this.updateAvatarSrc(avatarElement, logoUrl);
                }
            });
        }
    }

    extractUserId(element) {
        // Sprawdź różne sposoby przechowywania user ID
        let userId = null;

        // Z data attributes
        userId = element.dataset.userId || 
                element.dataset.user || 
                element.dataset.authorId;

        if (userId) return userId;

        // Z klas CSS
        const classList = element.className;
        const userIdMatch = classList.match(/user-(\d+)/);
        if (userIdMatch) return userIdMatch[1];

        // Z URL awatara
        const src = element.src || element.getAttribute('src');
        if (src) {
            const urlMatch = src.match(/[\?&]user[_-]?id=(\d+)/i);
            if (urlMatch) return urlMatch[1];
        }

        // Z href w parent link
        const parentLink = element.closest('a[href*="user"], a[href*="profile"], a[href*="member"]');
        if (parentLink) {
            const href = parentLink.getAttribute('href');
            const hrefMatch = href.match(/[\?&\/](\d+)[\?&\/]?/);
            if (hrefMatch) return hrefMatch[1];
        }

        // Z alt text
        const alt = element.alt || element.getAttribute('alt');
        if (alt) {
            const altMatch = alt.match(/user[\s-]?(\d+)/i);
            if (altMatch) return altMatch[1];
        }

        // Sprawdź parent elements
        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            if (parent.dataset.userId || parent.dataset.user) {
                return parent.dataset.userId || parent.dataset.user;
            }
            
            const parentClass = parent.className;
            if (parentClass) {
                const parentMatch = parentClass.match(/user-(\d+)/);
                if (parentMatch) return parentMatch[1];
            }
            
            parent = parent.parentElement;
        }

        return null;
    }

    async getCompanyLogo(userId) {
        // Sprawdź cache
        if (this.companyLogos[userId]) {
            return this.companyLogos[userId];
        }

        try {
            const response = await fetch(rop_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rop_get_company_logo',
                    user_id: userId,
                    nonce: rop_ajax.nonce
                })
            });

            const data = await response.json();
            
            if (data.success && data.data.logo_url) {
                this.companyLogos[userId] = data.data.logo_url;
                return data.data.logo_url;
            }
        } catch (error) {
            console.log('Error fetching company logo:', error);
        }

        return null;
    }

    updateAvatarSrc(element, logoUrl) {
        if (element.tagName === 'IMG') {
            element.src = logoUrl;
            element.srcset = ''; // Wyczyść srcset
        } else if (element.style) {
            element.style.backgroundImage = `url(${logoUrl})`;
            element.style.backgroundSize = 'cover';
            element.style.backgroundPosition = 'center';
        }

        // Dodaj klasy CSS dla lepszego stylu
        element.classList.add('rop-company-logo');
        element.style.borderRadius = '50%';
        element.style.objectFit = 'cover';
    }

    interceptAjaxRequests() {
        // Przechwytuj AJAX requests Better Messages
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            const response = await originalFetch.apply(this, args);
            
            // Po każdym AJAX request sprawdź nowe awatary
            setTimeout(() => {
                this.replaceExistingAvatars();
            }, 100);
            
            return response;
        };

        // Również dla jQuery AJAX
        if (window.jQuery) {
            const originalAjax = jQuery.ajax;
            jQuery.ajax = function(...args) {
                const xhr = originalAjax.apply(this, args);
                
                xhr.always(() => {
                    setTimeout(() => {
                        window.ropAvatarReplacer?.replaceExistingAvatars();
                    }, 100);
                });
                
                return xhr;
            };
        }
    }
}

// Inicjalizuj gdy strona jest gotowa
window.ropAvatarReplacer = new RopAvatarReplacer();
class RopAvatarReplacer {
    constructor() {
        this.companyLogos = {};
        this.userIds = {};
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.start());
        } else {
            this.start();
        }
    }

    start() {
        console.log('ROP Avatar Replacer: Starting...');

        this.observeMessages();
        this.replaceExistingAvatars();
        this.interceptAjaxRequests();
    }

    observeMessages() {
        const containers = [
            '.better-messages-list',
            '.bm-messages-wrap',
            '.better-messages-conversation',
            '.bm-conversation-wrap',
            '#better-messages-container',
            '.better-messages',
            '.bm-pic'
        ];

        containers.forEach(selector => {
            const container = document.querySelector(selector);
            if (container) {
                this.observeContainer(container);
            }
        });

        this.observeContainer(document.body);
    }

    observeContainer(container) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
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
        const avatarSelectors = [
            '.bm-pic img',
            '.better-messages-avatar img',
            '.bm-avatar img',
            '.avatar.bbpm-avatar',
            '.bm-pic .avatar',
            '.better-messages-list .avatar',
            'img[src*="default_avatar"]',
            'img.avatar'
        ];

        avatarSelectors.forEach(selector => {
            const avatars = document.querySelectorAll(selector);
            avatars.forEach(avatar => this.replaceAvatar(avatar));
        });
    }

    replaceAvatarsInElement(element) {
        if (this.isAvatarElement(element)) {
            this.replaceAvatar(element);
        }
        const avatarSelectors = [
            '.bm-pic img',
            '.better-messages-avatar img',
            '.bm-avatar img',
            '.avatar.bbpm-avatar',
            '.bm-pic .avatar',
            'img[src*="default_avatar"]',
            'img.avatar'
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
            element.matches('.bm-avatar img') ||
            element.matches('img[src*="default_avatar"]')
        );
    }

    replaceAvatar(avatarElement) {
        if (!avatarElement || avatarElement.dataset.ropProcessed) {
            return;
        }

        avatarElement.dataset.ropProcessed = 'true';
        const userId = this.extractUserId(avatarElement);
        
        if (userId) {
            if (isNaN(userId)) {
                this.getUserIdByUsername(userId).then(realUserId => {
                    if (realUserId) {
                        this.getCompanyLogo(realUserId).then(logoUrl => {
                            if (logoUrl) {
                                this.updateAvatarSrc(avatarElement, logoUrl);
                            }
                        });
                    }
                });
            } else {
                this.getCompanyLogo(userId).then(logoUrl => {
                    if (logoUrl) {
                        this.updateAvatarSrc(avatarElement, logoUrl);
                    }
                });
            }
        }
    }

    extractUserId(element) {
        let userId = null;

        userId = element.dataset.userId || 
                element.dataset.user || 
                element.dataset.authorId;

        if (userId) return userId;

        const classList = element.className;
        const userIdMatch = classList.match(/user-(\d+)/);
        if (userIdMatch) return userIdMatch[1];

        const src = element.src || element.getAttribute('src');
        if (src) {
            const patterns = [
                /[\?&]user[_-]?id=(\d+)/i,
                /\/user[_-]?(\d+)[\/\?]/i,
                /\/(\d+)\/avatar/i,
                /avatar.*user.*(\d+)/i,
                /gravatar\.com.*s=(\d+)/i
            ];
            
            for (const pattern of patterns) {
                const match = src.match(pattern);
                if (match) return match[1];
            }
        }

        const parentLink = element.closest('a[href*="user"], a[href*="profile"], a[href*="member"], a[href*="panel-czlonka"]');
        if (parentLink) {
            const href = parentLink.getAttribute('href');
            const hrefPatterns = [
                /\/panel-czlonka\/([^\/\?]+)/,
                /\/user\/([^\/\?]+)/,
                /\/profile\/([^\/\?]+)/,
                /[\?&\/](\d+)[\?&\/]?/
            ];
            
            for (const pattern of hrefPatterns) {
                const match = href.match(pattern);
                if (match) {
                    return match[1];
                }
            }
        }

        const alt = element.alt || element.getAttribute('alt');
        if (alt) {
            const altPatterns = [
                /user[\s-]?(\d+)/i,
                /profilowe\s+(.+)/i,
                /Zdjęcie profilowe\s+(.+)/i,
                /(.+)\s+avatar/i
            ];
            
            for (const pattern of altPatterns) {
                const match = alt.match(pattern);
                if (match) {
                    const extracted = match[1].trim();
                    if (extracted && extracted !== 'admin' && extracted !== 'user') {
                        return extracted;
                    }
                }
            }
        }

        const title = element.title || element.getAttribute('title');
        if (title) {
            const titleMatch = title.match(/(.+)/);
            if (titleMatch) {
                return titleMatch[1].trim();
            }
        }

        let parent = element.parentElement;
        let depth = 0;
        while (parent && parent !== document.body && depth < 5) {
            if (parent.dataset.userId || parent.dataset.user) {
                return parent.dataset.userId || parent.dataset.user;
            }
            
            const parentClass = parent.className;
            if (parentClass) {
                const parentMatch = parentClass.match(/user-(\d+)/);
                if (parentMatch) return parentMatch[1];
            }
            
            parent = parent.parentElement;
            depth++;
        }

        return null;
    }

    async getUserIdByUsername(username) {
        if (this.userIds[username]) {
            return this.userIds[username];
        }

        try {
            const formData = new FormData();
            formData.append('action', 'rop_get_user_id_by_username');
            formData.append('username', username);
            formData.append('nonce', rop_ajax.nonce);

            const response = await fetch(rop_ajax.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success && data.data.user_id) {
                this.userIds[username] = data.data.user_id;
                return data.data.user_id;
            }
        } catch (error) {
            console.log('Error fetching user ID by username:', error);
        }

        return null;
    }

    async getCompanyLogo(userId) {
        if (this.companyLogos[userId]) {
            return this.companyLogos[userId];
        }

        try {
            const formData = new FormData();
            formData.append('action', 'rop_get_company_logo');
            formData.append('user_id', userId);
            formData.append('nonce', rop_ajax.nonce);

            const response = await fetch(rop_ajax.ajax_url, {
                method: 'POST',
                body: formData
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
            element.srcset = '';
        } else if (element.style) {
            element.style.backgroundImage = `url(${logoUrl})`;
            element.style.backgroundSize = 'cover';
            element.style.backgroundPosition = 'center';
        }

        element.classList.add('rop-company-logo');
        element.style.borderRadius = '50%';
        element.style.objectFit = 'cover';
        
        console.log('ROP Avatar Replacer: Avatar updated successfully');
    }

    interceptAjaxRequests() {
        const originalFetch = window.fetch.bind(window);
        
        window.fetch = async (...args) => {
            const response = await originalFetch(...args);

            setTimeout(() => {
                this.replaceExistingAvatars();
            }, 500);
            
            return response;
        };

        if (window.jQuery) {
            const originalAjax = jQuery.ajax;
            const self = this;
            
            jQuery.ajax = function(...args) {
                const xhr = originalAjax.apply(this, args);
                
                xhr.always(() => {
                    setTimeout(() => {
                        self.replaceExistingAvatars();
                    }, 500);
                });
                
                return xhr;
            };
        }
    }
}

window.ropAvatarReplacer = new RopAvatarReplacer();
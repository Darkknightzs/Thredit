/**
 * TechForum - Client Interactions (Vanilla JS)
 * - Theme Switcher with localStorage persistence
 * - AJAX Voting (Reddit/StackOverflow style)
 * - Action Confirmations
 */

document.addEventListener('DOMContentLoaded', () => {
    // -----------------------------------------------------------------
    // 1. Dark & Light Theme Switcher
    // -----------------------------------------------------------------
    const themeToggleBtn = document.getElementById('theme-toggle');
    const htmlElement = document.documentElement;

    const savedTheme = localStorage.getItem('forum_theme') || 'light';
    htmlElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('forum_theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggleBtn) return;
        if (theme === 'dark') {
            // Sun icon (Clean, centered rays)
            themeToggleBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>`;
            themeToggleBtn.setAttribute('title', 'Switch to Light Theme');
        } else {
            // Moon icon (Clean, smooth crescent)
            themeToggleBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>`;
            themeToggleBtn.setAttribute('title', 'Switch to Dark Theme');
        }
    }

    // -----------------------------------------------------------------
    // 2. AJAX Upvote / Downvote Handling (Supports Reddit & Classic Buttons)
    // -----------------------------------------------------------------
    document.querySelectorAll('.vote-btn, .reddit-vote-btn').forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();

            const targetType = button.getAttribute('data-target-type');
            const targetId = button.getAttribute('data-target-id');
            const voteValue = button.getAttribute('data-vote');

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const container = button.closest('.vote-col, .reddit-vote-column');
            if (!container) return;

            const scoreDisplay = container.querySelector('.vote-score, .reddit-vote-number');
            const upBtn = container.querySelector('.vote-btn[data-vote="1"], .reddit-vote-btn[data-vote="1"]');
            const downBtn = container.querySelector('.vote-btn[data-vote="-1"], .reddit-vote-btn[data-vote="-1"]');

            const voteApiUrl = button.getAttribute('data-vote-url') || '../public/vote.php';

            try {
                button.disabled = true;

                const response = await fetch(voteApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: new URLSearchParams({
                        csrf_token: csrfToken,
                        target_type: targetType,
                        target_id: targetId,
                        value: voteValue
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    if (response.status === 401) {
                        window.location.href = data.redirect || 'login.php';
                        return;
                    }
                    showToast(data.error || 'Failed to submit vote.');
                    return;
                }

                // Update score display
                if (scoreDisplay) {
                    scoreDisplay.textContent = data.new_score;
                    scoreDisplay.classList.remove('score-positive', 'score-negative', 'score-up', 'score-down');
                    if (data.new_score > 0) {
                        scoreDisplay.classList.add('score-positive', 'score-up');
                    } else if (data.new_score < 0) {
                        scoreDisplay.classList.add('score-negative', 'score-down');
                    }
                }

                // Update active state
                if (upBtn) {
                    upBtn.classList.remove('active-up', 'voted');
                }
                if (downBtn) {
                    downBtn.classList.remove('active-down', 'voted');
                }

                if (data.user_vote === 1 && upBtn) {
                    upBtn.classList.add('active-up', 'voted');
                } else if (data.user_vote === -1 && downBtn) {
                    downBtn.classList.add('active-down', 'voted');
                }

            } catch (err) {
                console.error('Vote failed:', err);
            } finally {
                button.disabled = false;
            }
        });
    });

    // -----------------------------------------------------------------
    // 3. Reddit Share Button (Copy Link to Clipboard + Toast)
    // -----------------------------------------------------------------
    document.querySelectorAll('.share-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const shareUrl = button.getAttribute('data-url') || window.location.href;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    showToast('Link copied to clipboard!');
                }).catch(() => {
                    fallbackCopy(shareUrl);
                });
            } else {
                fallbackCopy(shareUrl);
            }
        });
    });

    function fallbackCopy(text) {
        const tempInput = document.createElement('input');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        try {
            document.execCommand('copy');
            showToast('Link copied to clipboard!');
        } catch (e) {
            showToast('Could not copy link automatically.');
        }
        document.body.removeChild(tempInput);
    }

    // -----------------------------------------------------------------
    // 4. Mobile Menu Drawer Toggle
    // -----------------------------------------------------------------
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const leftSidebar = document.getElementById('left-nav-sidebar');

    if (mobileMenuToggle && leftSidebar) {
        let backdrop = document.querySelector('.drawer-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'drawer-backdrop';
            document.body.appendChild(backdrop);
        }

        const toggleDrawer = (open) => {
            if (open) {
                leftSidebar.classList.add('drawer-open');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                leftSidebar.classList.remove('drawer-open');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        };

        mobileMenuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = leftSidebar.classList.contains('drawer-open');
            toggleDrawer(!isOpen);
        });

        backdrop.addEventListener('click', () => {
            toggleDrawer(false);
        });

        // Close drawer when clicking outside
        document.addEventListener('click', (e) => {
            if (leftSidebar.classList.contains('drawer-open') &&
                !leftSidebar.contains(e.target) &&
                !mobileMenuToggle.contains(e.target)) {
                toggleDrawer(false);
            }
        });
    }

    // -----------------------------------------------------------------
    // 5. Action Confirmations
    // -----------------------------------------------------------------
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', (e) => {
            const message = element.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});

/**
 * Shows a temporary Reddit-style toast notification
 * @param {string} message
 */
function showToast(message) {
    let toast = document.getElementById('toast-notification');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.className = 'toast-popup';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.display = 'block';
    toast.classList.add('toast-show');

    setTimeout(() => {
        toast.classList.remove('toast-show');
        setTimeout(() => {
            toast.style.display = 'none';
        }, 300);
    }, 2500);
}

/**
 * Toggles visibility of inline reply edit form
 * @param {number} replyId
 */
function toggleEditReply(replyId) {
    const editContainer = document.getElementById('edit-reply-' + replyId);
    if (!editContainer) return;
    const isHidden = editContainer.style.display === 'none' || editContainer.style.display === '';
    editContainer.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        const textarea = editContainer.querySelector('textarea');
        if (textarea) textarea.focus();
    }
}

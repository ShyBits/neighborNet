// Chat Box JavaScript
(function() {
    'use strict';
    
    let chatBox = null;
    let currentChatId = null;
    let currentContactId = null;
    let pollingInterval = null;
    let contactsCache = [];
    let messagesCache = new Map();
    let currentUserId = null; // Will be set from API responses
    let chatParticipants = new Map(); // Map of chatId -> [userIds]
    let isDragging = false;
    let selectedFiles = []; // Array to store multiple selected files
    let dragOffset = { x: 0, y: 0 };
    let isMinimized = false;
    let isResizing = false;
    let resizeStartX = 0;
    let resizeStartY = 0;
    let resizeStartWidth = 0;
    let resizeStartHeight = 0;
    let lastMessageIdCache = new Map(); // Cache für letzte Nachrichten-ID pro Chat
    let currentLoadMessagesAbortController = null; // AbortController for current loadMessages request
    let currentCreateChatAbortController = null; // AbortController for current createChat request
    let currentMarkAsReadAbortController = null; // AbortController for current markMessagesAsRead request
    let currentCheckMessagesAbortController = null; // AbortController for current checkForNewMessages request
    let heartbeatInterval = null; // Interval for user activity heartbeat
    let notificationInterval = null; // Interval for notification updates
    let currentMediaFiles = []; // Array of all media files in current chat
    let currentMediaIndex = 0; // Current index in media files array
    let totalUnreadCount = 0; // Total unread messages count
    let showFavoritesOnly = false; // Filter for favorites
    let showArchivedFavoritesOnly = false; // Filter for archived favorites
    let currentContactIsFavorite = false; // Track if current contact is favorited
    let contextMenuChatId = null; // Track which chat the context menu is for
    let contextMenuItem = null; // Track which item the context menu is for
    
    // Initialize chat when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeChatBox();
    });
    
    // Stop heartbeat and notifications when page is unloaded
    window.addEventListener('beforeunload', function() {
        stopHeartbeat();
        stopNotificationPolling();
    });
    
    // Also stop on pagehide for better mobile support
    window.addEventListener('pagehide', function() {
        stopHeartbeat();
        stopNotificationPolling();
    });
    
    function initializeChatBox() {
        // Create or get chat box element
        chatBox = document.getElementById('chatBox');
        if (!chatBox) {
            return;
        }
        
        // Initialize drag functionality
        initDrag();
        
        // Initialize resize functionality
        initResize();
        
        // Initialize buttons
        initButtons();
        
        // Initialize chat input
        initChatInput();
        
        // Load contacts
        loadContacts(true);
        
        // Setup infinite scroll
        setupContactsScroll();
        
        // Check if archived tab should be visible
        checkArchivedTabVisibility();
        setupNewContactsScroll();
        
        // Start polling for new messages
        startPolling();
        
        // Start heartbeat for user activity
        startHeartbeat();
        
        // Start notification polling
        startNotificationPolling();
        
        // Encryption removed
        
        // Initialize media lightbox
        initMediaLightbox();
        
        // Update toggle button state on initialization
        updateChatToggleButton();
    }
    
    // Drag functionality
    function initDrag() {
        const header = chatBox.querySelector('.chat-box-header');
        if (!header) return;
        
        header.addEventListener('mousedown', function(e) {
            // Don't start drag if clicking on buttons
            if (e.target.closest('.chat-box-header-actions') || 
                e.target.closest('.chat-box-btn') ||
                e.target.closest('button') ||
                e.target.closest('svg')) {
                return;
            }
            
            isDragging = true;
            chatBox.classList.add('dragging');
            
            const rect = chatBox.getBoundingClientRect();
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            
            // Remove transform to use absolute positioning
            chatBox.style.transform = 'none';
            chatBox.style.left = rect.left + 'px';
            chatBox.style.top = rect.top + 'px';
            
            document.addEventListener('mousemove', handleDrag);
            document.addEventListener('mouseup', stopDrag);
            
            e.preventDefault();
            e.stopPropagation();
        });
    }
    
    function handleDrag(e) {
        if (!isDragging) return;
        
        // Calculate max positions based on current box size (works for both minimized and expanded)
        const maxX = window.innerWidth - chatBox.offsetWidth;
        const maxY = window.innerHeight - chatBox.offsetHeight;
        
        let x = e.clientX - dragOffset.x;
        let y = e.clientY - dragOffset.y;
        
        x = Math.max(0, Math.min(x, maxX));
        y = Math.max(0, Math.min(y, maxY));
        
        chatBox.style.left = x + 'px';
        chatBox.style.top = y + 'px';
        
        e.preventDefault();
    }
    
    function stopDrag() {
        if (isDragging) {
            isDragging = false;
            chatBox.classList.remove('dragging');
        }
        document.removeEventListener('mousemove', handleDrag);
        document.removeEventListener('mouseup', stopDrag);
    }
    
    // Resize functionality
    function initResize() {
        const resizeHandle = document.getElementById('chatBoxResizeHandle');
        if (!resizeHandle) return;
        
        resizeHandle.addEventListener('mousedown', function(e) {
            if (isMinimized) return; // Don't allow resize when minimized
            
            isResizing = true;
            chatBox.classList.add('resizing');
            
            const rect = chatBox.getBoundingClientRect();
            resizeStartX = e.clientX;
            resizeStartY = e.clientY;
            resizeStartWidth = rect.width;
            resizeStartHeight = rect.height;
            
            // Remove transform to use absolute positioning
            const currentLeft = chatBox.style.left || (rect.left + rect.width / 2) + 'px';
            const currentTop = chatBox.style.top || (rect.top + rect.height / 2) + 'px';
            
            chatBox.style.transform = 'none';
            chatBox.style.left = (rect.left) + 'px';
            chatBox.style.top = (rect.top) + 'px';
            
            document.addEventListener('mousemove', handleResize);
            document.addEventListener('mouseup', stopResize);
            
            e.preventDefault();
            e.stopPropagation();
        });
    }
    
    function handleResize(e) {
        if (!isResizing || isMinimized) return;
        
        const deltaX = e.clientX - resizeStartX;
        const deltaY = e.clientY - resizeStartY;
        
        let newWidth = resizeStartWidth + deltaX;
        let newHeight = resizeStartHeight + deltaY;
        
        // Apply min/max constraints
        // Allow smaller width to enable horizontal resizing
        // Minimum: 200px (contacts) + 200px (messages) = 400px total
        const minWidth = 400;
        const minHeight = 300;
        const maxWidth = window.innerWidth - 40;
        const maxHeight = window.innerHeight - 40;
        
        newWidth = Math.max(minWidth, Math.min(newWidth, maxWidth));
        newHeight = Math.max(minHeight, Math.min(newHeight, maxHeight));
        
        // Check if we're hitting screen boundaries
        const rect = chatBox.getBoundingClientRect();
        const currentLeft = parseFloat(chatBox.style.left) || rect.left;
        const currentTop = parseFloat(chatBox.style.top) || rect.top;
        
        // Adjust position if resizing would go beyond screen
        if (currentLeft + newWidth > window.innerWidth - 20) {
            newWidth = window.innerWidth - currentLeft - 20;
        }
        if (currentTop + newHeight > window.innerHeight - 20) {
            newHeight = window.innerHeight - currentTop - 20;
        }
        
        chatBox.style.width = newWidth + 'px';
        chatBox.style.height = newHeight + 'px';
    }
    
    function stopResize() {
        if (isResizing) {
            isResizing = false;
            chatBox.classList.remove('resizing');
        }
        document.removeEventListener('mousemove', handleResize);
        document.removeEventListener('mouseup', stopResize);
    }
    
    // Initialize buttons
    function initButtons() {
        const toggleBtn = document.getElementById('chatToggleBtn');
        const toggleBtnMobile = document.getElementById('chatToggleBtnMobile');
        const closeBtn = document.getElementById('chatCloseBtn');
        const minimizeBtn = document.getElementById('chatMinimizeBtn');
        
        function handleToggleChat() {
                if (chatBox.classList.contains('hidden')) {
                    showChatBox();
                } else {
                    hideChatBox();
                }
        }
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', handleToggleChat);
        }
        
        if (toggleBtnMobile) {
            toggleBtnMobile.addEventListener('click', handleToggleChat);
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', hideChatBox);
        }
        
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', toggleMinimize);
        }
        
        // Tab navigation
        const contactsTab = document.getElementById('chatContactsTab');
        const archivedTab = document.getElementById('chatArchivedTab');
        const newContactsTab = document.getElementById('chatNewContactsTab');
        
        if (contactsTab) {
            contactsTab.addEventListener('click', function() {
                switchView('contacts');
            });
        }
        
        if (archivedTab) {
            archivedTab.addEventListener('click', function() {
                switchView('archived');
            });
        }
        
        if (newContactsTab) {
            newContactsTab.addEventListener('click', function() {
                switchView('new-contacts');
            });
        }
        
        // Search input
        const searchInput = document.getElementById('chatSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', handleSearch);
            searchInput.addEventListener('keyup', handleSearch);
            searchInput.addEventListener('change', handleSearch);
        }
        
        // Favorites filter button
        const favoritesFilterBtn = document.getElementById('chatFavoritesFilterBtn');
        if (favoritesFilterBtn) {
            favoritesFilterBtn.addEventListener('click', toggleFavoritesFilter);
        }
        
        const archivedFavoritesFilterBtn = document.getElementById('chatArchivedFavoritesFilterBtn');
        if (archivedFavoritesFilterBtn) {
            archivedFavoritesFilterBtn.addEventListener('click', toggleArchivedFavoritesFilter);
        }
        
        // Favorite button in chat header
        const favoriteBtn = document.getElementById('chatFavoriteBtn');
        const favoriteBtnMobile = document.getElementById('chatFavoriteBtnMobile');
        if (favoriteBtn) {
            favoriteBtn.addEventListener('click', toggleFavorite);
        }
        if (favoriteBtnMobile) {
            favoriteBtnMobile.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleFavorite();
            });
            // Also add touch event for better mobile support
            favoriteBtnMobile.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleFavorite();
            });
        }
        
        // Context menu handlers
        // Initialize context menu handlers (only once)
        initContextMenuHandlers();
        
        // Remove confirmation modal handlers
        const removeModal = document.getElementById('chatRemoveConfirmModal');
        const removeCancelBtn = document.getElementById('chatRemoveCancelBtn');
        const removeConfirmBtn = document.getElementById('chatRemoveConfirmBtn');
        
        if (removeCancelBtn) {
            removeCancelBtn.addEventListener('click', function() {
                hideRemoveConfirmModal();
            });
        }
        
        if (removeConfirmBtn) {
            removeConfirmBtn.addEventListener('click', function() {
                hideRemoveConfirmModal();
                handleRemoveChat();
            });
        }
        
        // Close modal when clicking outside
        if (removeModal) {
            removeModal.addEventListener('click', function(e) {
                if (e.target === removeModal) {
                    hideRemoveConfirmModal();
                }
            });
        }
        
        // Back button for mobile navigation
        const backBtn = document.getElementById('chatBackBtn');
        if (backBtn) {
            backBtn.addEventListener('click', showMobileContactsView);
        }
        
        // Mobile exit button in chatbox
        const chatMobileExitBtn = document.getElementById('chatMobileExitBtn');
        if (chatMobileExitBtn) {
            chatMobileExitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideChatBox();
            });
        }
    }
    
    // Mobile navigation functions
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    function showMobileMessagesView() {
        if (isMobile()) {
            chatBox.classList.add('show-messages');
            
            // Show messages UI elements
            const chatEmptyState = document.getElementById('chatEmptyState');
            const chatMessagesMobileHeader = document.getElementById('chatMessagesMobileHeader');
            const chatMessagesContainer = document.getElementById('chatMessagesContainer');
            const chatInputContainer = document.getElementById('chatInputContainer');
            
            if (chatEmptyState) chatEmptyState.style.display = 'none';
            if (chatMessagesMobileHeader) chatMessagesMobileHeader.style.display = 'flex';
            if (chatMessagesContainer) chatMessagesContainer.style.display = 'flex';
            if (chatInputContainer) chatInputContainer.style.display = 'block';
        }
    }
    
    function showMobileContactsView() {
        if (isMobile()) {
            chatBox.classList.remove('show-messages');
            updateMobileHeaderTitle('Chat');
            
            // Hide messages UI elements
            const chatEmptyState = document.getElementById('chatEmptyState');
            const chatMessagesHeader = document.getElementById('chatMessagesHeader');
            const chatMessagesMobileHeader = document.getElementById('chatMessagesMobileHeader');
            const chatMessagesContainer = document.getElementById('chatMessagesContainer');
            const chatInputContainer = document.getElementById('chatInputContainer');
            
            if (chatEmptyState) chatEmptyState.style.display = 'flex';
            if (chatMessagesHeader) chatMessagesHeader.style.display = 'none';
            if (chatMessagesMobileHeader) chatMessagesMobileHeader.style.display = 'none';
            if (chatMessagesContainer) chatMessagesContainer.style.display = 'none';
            if (chatInputContainer) chatInputContainer.style.display = 'none';
            
            // Remove active state from all contacts so other chats can be opened
            document.querySelectorAll('.chat-contact-item, .chat-new-contact-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Clear file preview if any
            removeFilePreview();
            
            // Clear input field when going back to contacts view
            const input = document.getElementById('chatInput');
            if (input) {
                input.value = '';
                input.style.height = 'auto';
            }
            selectedFiles = [];
            
            // Don't clear currentChatId and currentContactId here - we want to allow reopening the same chat
            // The contacts are now active again and can be clicked to open chats
        }
    }
    
    function updateMobileHeaderTitle(title) {
        if (!isMobile()) return;
        // Update desktop title
        const titleEl = document.querySelector('.chat-box-title');
        if (titleEl) {
            titleEl.textContent = title;
        }
        // Update mobile header title only if we're in messages view
        // Don't change it if we're in contacts view
        const mobileHeaderTitle = document.querySelector('.chat-mobile-header-title');
        const isInMessagesView = chatBox && chatBox.classList.contains('show-messages');
        if (mobileHeaderTitle && isInMessagesView) {
            // Only update if we're actually showing messages
            // Otherwise keep "ChatNet" in contacts view
            if (title !== 'ChatNet') {
                mobileHeaderTitle.textContent = title;
            }
        }
    }
    
    // Switch between contacts, archived, and new contacts view
    function switchView(view) {
        const contactsTab = document.getElementById('chatContactsTab');
        const archivedTab = document.getElementById('chatArchivedTab');
        const newContactsTab = document.getElementById('chatNewContactsTab');
        const contactsList = document.getElementById('chatContactsList');
        const archivedList = document.getElementById('chatArchivedContactsList');
        const newContactsList = document.getElementById('chatNewContactsList');
        const searchInput = document.getElementById('chatSearchInput');
        const favoritesFilterBtn = document.getElementById('chatFavoritesFilterBtn');
        
        if (view === 'contacts') {
            // Show contacts view
            if (contactsTab) contactsTab.classList.add('active');
            if (archivedTab) archivedTab.classList.remove('active');
            if (newContactsTab) newContactsTab.classList.remove('active');
            if (contactsList) contactsList.style.display = 'flex';
            if (archivedList) archivedList.style.display = 'none';
            if (newContactsList) newContactsList.style.display = 'none';
            // Show favorites filter button in contacts tab, hide archived favorites filter button
            if (favoritesFilterBtn) favoritesFilterBtn.style.display = 'flex';
            const archivedFavoritesFilterBtn = document.getElementById('chatArchivedFavoritesFilterBtn');
            if (archivedFavoritesFilterBtn) archivedFavoritesFilterBtn.style.display = 'none';
            if (searchInput) {
                searchInput.placeholder = 'Nach Kontakten suchen...';
                searchInput.value = '';
                handleSearch({ target: searchInput });
            }
        } else if (view === 'archived') {
            // Show archived contacts view
            if (contactsTab) contactsTab.classList.remove('active');
            if (archivedTab) archivedTab.classList.add('active');
            if (newContactsTab) newContactsTab.classList.remove('active');
            if (contactsList) contactsList.style.display = 'none';
            if (archivedList) archivedList.style.display = 'flex';
            if (newContactsList) newContactsList.style.display = 'none';
            // Hide regular favorites filter button, show archived favorites filter button
            if (favoritesFilterBtn) favoritesFilterBtn.style.display = 'none';
            const archivedFavoritesFilterBtn = document.getElementById('chatArchivedFavoritesFilterBtn');
            if (archivedFavoritesFilterBtn) archivedFavoritesFilterBtn.style.display = 'flex';
            // Reset regular favorites filter when switching to archived (but keep archived filter state)
            showFavoritesOnly = false;
            if (favoritesFilterBtn) favoritesFilterBtn.classList.remove('active');
            if (searchInput) {
                searchInput.placeholder = 'Nach archivierten Kontakten suchen...';
                searchInput.value = '';
            }
            
            // Always reload archived contacts when switching to this tab
            loadArchivedContacts(true);
        } else if (view === 'new-contacts') {
            // Show new contacts view
            if (contactsTab) contactsTab.classList.remove('active');
            if (archivedTab) archivedTab.classList.remove('active');
            if (newContactsTab) newContactsTab.classList.add('active');
            if (contactsList) contactsList.style.display = 'none';
            if (archivedList) archivedList.style.display = 'none';
            if (newContactsList) newContactsList.style.display = 'flex';
            // Hide favorites filter buttons in new contacts tab
            if (favoritesFilterBtn) favoritesFilterBtn.style.display = 'none';
            const archivedFavoritesFilterBtn = document.getElementById('chatArchivedFavoritesFilterBtn');
            if (archivedFavoritesFilterBtn) archivedFavoritesFilterBtn.style.display = 'none';
            // Reset favorites filter when switching to new contacts
            showFavoritesOnly = false;
            if (favoritesFilterBtn) favoritesFilterBtn.classList.remove('active');
            if (searchInput) {
                searchInput.placeholder = 'Nach Kontakten suchen...';
                searchInput.value = '';
                handleSearch({ target: searchInput });
            }
            
            // Always reload new contacts when switching to this tab
            // This ensures the list is up-to-date (e.g., after sending a message)
            loadNewContacts(true);
        }
    }
    
    function showChatBox() {
        chatBox.classList.remove('hidden');
        if (isMinimized) {
            chatBox.classList.remove('minimized');
            isMinimized = false;
        }
        
        // Center chat box on screen
        centerChatBox();
        
        // On mobile, always show contacts view first
        if (isMobile()) {
            showMobileContactsView();
            // Prevent body scrolling when chat is open
            document.body.classList.add('chat-box-open');
            document.documentElement.classList.add('chat-box-open');
        }
        
        loadContacts(true);
        
        // Update toggle button state
        updateChatToggleButton();
    }
    
    function centerChatBox() {
        // Only center if not dragged yet (no custom left/top position)
        const currentLeft = chatBox.style.left;
        const currentTop = chatBox.style.top;
        
        if (!currentLeft || !currentTop || currentLeft === '' || currentTop === '') {
            // Center on screen
            chatBox.style.transform = 'translate(-50%, -50%)';
            chatBox.style.left = '50%';
            chatBox.style.top = '50%';
            chatBox.style.right = 'auto';
            chatBox.style.bottom = 'auto';
        }
    }
    
    function hideChatBox() {
        chatBox.classList.add('hidden');
        stopPolling();
        hideAnfrageStatusBar(); // Hide status bar when closing chat
        
        // On mobile, reset to contacts view when closing
        if (isMobile()) {
            showMobileContactsView();
            // Re-enable body scrolling when chat is closed
            document.body.classList.remove('chat-box-open');
            document.documentElement.classList.remove('chat-box-open');
        }
        
        // Update toggle button state
        updateChatToggleButton();
        
        // Continue notification polling even when chat is closed
        // This ensures badge stays updated
    }
    
    function toggleMinimize() {
        isMinimized = !isMinimized;
        if (isMinimized) {
            chatBox.classList.add('minimized');
        } else {
            chatBox.classList.remove('minimized');
            loadContacts(true);
        }
        // Update toggle button state - chat is still open even when minimized
        updateChatToggleButton();
    }
    
    // Update chat toggle button appearance based on chat box state
    function updateChatToggleButton() {
        const toggleBtn = document.getElementById('chatToggleBtn');
        const toggleBtnMobile = document.getElementById('chatToggleBtnMobile');
        if ((!toggleBtn && !toggleBtnMobile) || !chatBox) return;
        
        // Chat is open if it's not hidden (even if minimized)
        const isChatOpen = !chatBox.classList.contains('hidden');
        
        if (toggleBtn) {
        if (isChatOpen) {
            toggleBtn.classList.add('chat-open');
        } else {
            toggleBtn.classList.remove('chat-open');
            }
        }
        
        if (toggleBtnMobile) {
            if (isChatOpen) {
                toggleBtnMobile.classList.add('chat-open');
            } else {
                toggleBtnMobile.classList.remove('chat-open');
            }
        }
        
        // Update badge when chat state changes
        updateChatToggleBadge(totalUnreadCount);
    }
    
    async function handleSearch(e) {
        if (!e || !e.target) return;
        
        const query = e.target.value.trim().toLowerCase();
        const newContactsTab = document.getElementById('chatNewContactsTab');
        const archivedTab = document.getElementById('chatArchivedTab');
        const isNewContactsView = newContactsTab && newContactsTab.classList.contains('active');
        const isArchivedView = archivedTab && archivedTab.classList.contains('active');
        
        if (isNewContactsView) {
            // Search in new contacts
            searchNewContacts(query);
        } else if (isArchivedView) {
            // Search in archived contacts
            let filtered = archivedContactsCache;
            
            // Apply search filter
            if (query !== '') {
                filtered = filtered.filter(contact => {
                    const name = (contact.name || '').toLowerCase();
                    const username = (contact.username || '').toLowerCase();
                    return name.includes(query) || username.includes(query);
                });
            }
            
            // Apply favorites filter
            if (showArchivedFavoritesOnly) {
                filtered = filtered.filter(contact => contact.is_favorite === true);
            }
            
            await displayArchivedContacts(filtered, true);
        } else {
            // Search in existing contacts
            let filtered = contactsCache;
            
            // Apply search filter
            if (query !== '') {
                filtered = filtered.filter(contact => {
                    const name = (contact.name || '').toLowerCase();
                    const username = (contact.username || '').toLowerCase();
                    return name.includes(query) || username.includes(query);
                });
            }
            
            // Apply favorites filter
            if (showFavoritesOnly) {
                filtered = filtered.filter(contact => contact.is_favorite === true);
            }
            
            await displayContacts(filtered);
        }
    }
    
    // Toggle favorites filter
    async function toggleFavoritesFilter() {
        // Only allow filtering in contacts tab
        const newContactsTab = document.getElementById('chatNewContactsTab');
        const isNewContactsView = newContactsTab && newContactsTab.classList.contains('active');
        if (isNewContactsView) {
            return; // Don't filter in new contacts view
        }
        
        showFavoritesOnly = !showFavoritesOnly;
        const favoritesFilterBtn = document.getElementById('chatFavoritesFilterBtn');
        if (favoritesFilterBtn) {
            if (showFavoritesOnly) {
                favoritesFilterBtn.classList.add('active');
            } else {
                favoritesFilterBtn.classList.remove('active');
            }
        }
        
        // Re-apply search if there's a query, otherwise just filter by favorites
        const searchInput = document.getElementById('chatSearchInput');
        if (searchInput && searchInput.value.trim() !== '') {
            // If there's a search query, re-apply search (which will also apply favorites filter)
            await handleSearch({ target: searchInput });
        } else {
            // Just filter by favorites
            if (showFavoritesOnly) {
                const filtered = contactsCache.filter(contact => contact.is_favorite === true);
                await displayContacts(filtered, true);
            } else {
                await displayContacts(contactsCache, true);
            }
        }
    }
    
    async function toggleArchivedFavoritesFilter() {
        showArchivedFavoritesOnly = !showArchivedFavoritesOnly;
        const archivedFavoritesFilterBtn = document.getElementById('chatArchivedFavoritesFilterBtn');
        if (archivedFavoritesFilterBtn) {
            if (showArchivedFavoritesOnly) {
                archivedFavoritesFilterBtn.classList.add('active');
            } else {
                archivedFavoritesFilterBtn.classList.remove('active');
            }
        }
        
        // Re-apply search if there's a query, otherwise just filter by favorites
        const searchInput = document.getElementById('chatSearchInput');
        if (searchInput && searchInput.value.trim() !== '') {
            // If there's a search query, re-apply search (which will also apply favorites filter)
            await handleSearch({ target: searchInput });
        } else {
            // Just filter by favorites
            if (showArchivedFavoritesOnly) {
                const filtered = archivedContactsCache.filter(contact => contact.is_favorite === true);
                await displayArchivedContacts(filtered, true);
            } else {
                await displayArchivedContacts(archivedContactsCache, true);
            }
        }
    }
    
    // Toggle favorite for current contact
    async function toggleFavorite() {
        // Only allow favoriting existing contacts (with chat_id)
        if (!currentContactId || !currentChatId) {
            console.log('Cannot favorite: currentContactId =', currentContactId, 'currentChatId =', currentChatId);
            return;
        }
        
        // Deaktiviere Button während der Verarbeitung
        const favoriteBtn = document.getElementById('chatFavoriteBtn');
        const favoriteBtnMobile = document.getElementById('chatFavoriteBtnMobile');
        const activeBtn = favoriteBtnMobile || favoriteBtn;
        
        if (activeBtn) {
            activeBtn.disabled = true;
        }
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const formData = new URLSearchParams();
            formData.append('contact_user_id', currentContactId);
            
            const response = await fetch(basePath + 'api/toggle-favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                currentContactIsFavorite = data.is_favorite;
                
                // Update button state
                if (favoriteBtn) {
                    if (data.is_favorite) {
                        favoriteBtn.classList.add('active');
                    } else {
                        favoriteBtn.classList.remove('active');
                    }
                }
                
                if (favoriteBtnMobile) {
                    if (data.is_favorite) {
                        favoriteBtnMobile.classList.add('active');
                    } else {
                        favoriteBtnMobile.classList.remove('active');
                    }
                }
                
                // Reload contacts to update favorite status
                await loadContacts(true);
                
                // Update contact in cache (both contacts and archived)
                const contactIndex = contactsCache.findIndex(c => c.user_id === currentContactId);
                if (contactIndex !== -1) {
                    contactsCache[contactIndex].is_favorite = data.is_favorite;
                }
                
                // Also update in archived cache
                const archivedIndex = archivedContactsCache.findIndex(c => c.user_id === currentContactId);
                if (archivedIndex !== -1) {
                    archivedContactsCache[archivedIndex].is_favorite = data.is_favorite;
                }
                
                // Re-display contacts to update the list with new favorite status and re-sort
                // Check which tab is active
                const archivedTab = document.getElementById('chatArchivedTab');
                const isArchivedView = archivedTab && archivedTab.classList.contains('active');
                
                if (isArchivedView) {
                    // Update archived contacts list
                    await displayArchivedContacts(archivedContactsCache, true);
                } else {
                    // Update regular contacts list
                    const searchInput = document.getElementById('chatSearchInput');
                    if (searchInput && searchInput.value.trim() !== '') {
                        // If there's a search query, re-apply search
                        handleSearch({ target: searchInput });
                    } else if (showFavoritesOnly) {
                        // If favorites filter is active, show only favorites
                        const filtered = contactsCache.filter(contact => contact.is_favorite === true);
                        await displayContacts(filtered, true);
                    } else {
                        // Otherwise, refresh the entire list (will be sorted with favorites first)
                        await displayContacts(contactsCache, true);
                    }
                }
            }
        } catch (error) {
            console.error('Fehler beim Favorisieren:', error);
            alert('Fehler beim Favorisieren. Bitte versuchen Sie es erneut.');
        } finally {
            // Reaktiviere Button
            if (activeBtn) {
                activeBtn.disabled = false;
            }
        }
    }
    
    // Show context menu for contact items
    // Initialize context menu handlers (only called once)
    function initContextMenuHandlers() {
        // Prevent multiple initializations
        if (window.chatContextMenuInitialized) return;
        window.chatContextMenuInitialized = true;
        
        const contextMenu = document.getElementById('chatContextMenu');
        if (!contextMenu) return;
        
        const contextArchive = document.getElementById('chatContextArchive');
        const contextDelete = document.getElementById('chatContextDelete');
        
        // Archive button handler
        if (contextArchive) {
            contextArchive.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideContextMenu();
                // Small delay to ensure menu is closed
                setTimeout(() => {
                    handleArchiveChat();
                }, 0);
            });
        }
        
        // Delete/Remove button handler
        if (contextDelete) {
            contextDelete.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideContextMenu();
                // Small delay to ensure menu is closed
                setTimeout(() => {
                    showRemoveConfirmModal();
                }, 0);
            });
        }
        
        // Hide context menu when clicking outside
        const handleClickOutside = function(e) {
            if (contextMenu && contextMenu.style.display === 'block') {
                // Check if click is outside the menu
                if (!contextMenu.contains(e.target)) {
                    // Always hide when clicking outside, regardless of what was clicked
                    hideContextMenu();
                }
            }
        };
        
        // Hide context menu on right-click outside
        const handleRightClickOutside = function(e) {
            if (contextMenu && contextMenu.style.display === 'block') {
                if (!contextMenu.contains(e.target)) {
                    hideContextMenu();
                }
            }
        };
        
        // Hide context menu on ESC key
        const handleEscapeKey = function(e) {
            if (e.key === 'Escape' && contextMenu && contextMenu.style.display === 'block') {
                hideContextMenu();
            }
        };
        
        // Hide context menu on scroll
        const handleScroll = function() {
            if (contextMenu && contextMenu.style.display === 'block') {
                hideContextMenu();
            }
        };
        
        // Add event listeners
        document.addEventListener('click', handleClickOutside, true);
        document.addEventListener('contextmenu', handleRightClickOutside, true);
        document.addEventListener('keydown', handleEscapeKey);
        
        const contactsList = document.getElementById('chatContactsList');
        if (contactsList) {
            contactsList.addEventListener('scroll', handleScroll);
        }
        
        const archivedList = document.getElementById('chatArchivedContactsList');
        if (archivedList) {
            archivedList.addEventListener('scroll', handleScroll);
        }
        
        // Store handlers for potential cleanup
        contextMenu._handlers = {
            click: handleClickOutside,
            contextmenu: handleRightClickOutside,
            keydown: handleEscapeKey,
            scroll: handleScroll
        };
    }
    
    function showContactContextMenu(e, chatId, item) {
        const contextMenu = document.getElementById('chatContextMenu');
        if (!contextMenu) return;
        
        // First, hide any existing menu
        hideContextMenu();
        
        // Prevent default context menu
        if (e.preventDefault) {
            e.preventDefault();
        }
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        contextMenuChatId = chatId;
        contextMenuItem = item;
        
        const archiveBtn = contextMenu.querySelector('#chatContextArchive');
        const isArchived = item.dataset.isArchived === '1';
        
        if (archiveBtn) {
            const archiveText = archiveBtn.querySelector('span');
            if (archiveText) {
                archiveText.textContent = isArchived ? 'Entarchivieren' : 'Archivieren';
            }
        }
        
        // Position menu
        const x = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
        const y = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
        
        // Use requestAnimationFrame to ensure DOM is ready
        requestAnimationFrame(() => {
            contextMenu.style.display = 'block';
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            
            // Adjust if menu goes off screen
            setTimeout(() => {
                const rect = contextMenu.getBoundingClientRect();
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;
                
                if (rect.right > windowWidth) {
                    contextMenu.style.left = (x - rect.width) + 'px';
                }
                if (rect.bottom > windowHeight) {
                    contextMenu.style.top = (y - rect.height) + 'px';
                }
            }, 0);
            
            // Close any other open context menus
            const allContextMenus = document.querySelectorAll('.chat-context-menu');
            allContextMenus.forEach(menu => {
                if (menu !== contextMenu && menu.style.display === 'block') {
                    menu.style.display = 'none';
                }
            });
        });
    }
    
    // Hide context menu
    function hideContextMenu() {
        const contextMenu = document.getElementById('chatContextMenu');
        if (contextMenu) {
            // Immediately hide the menu
            contextMenu.style.display = 'none';
            contextMenu.style.left = '';
            contextMenu.style.top = '';
        }
        contextMenuChatId = null;
        contextMenuItem = null;
    }
    
    // Handle archive chat
    async function handleArchiveChat() {
        if (!contextMenuChatId) return;
        
        const isArchived = contextMenuItem && contextMenuItem.dataset.isArchived === '1';
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const formData = new URLSearchParams();
            formData.append('chat_id', contextMenuChatId);
            formData.append('archive', isArchived ? '0' : '1');
            
            const response = await fetch(basePath + 'api/archive-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                // Ensure menu is closed
                hideContextMenu();
                
                // Preserve mobile header title if in contacts view
                const mobileHeaderTitle = document.querySelector('.chat-mobile-header-title');
                const preservedTitle = mobileHeaderTitle ? mobileHeaderTitle.textContent : null;
                
                // Reload contacts to reflect changes
                await loadContacts(true);
                
                // Restore mobile header title if it was changed
                if (preservedTitle && mobileHeaderTitle && mobileHeaderTitle.textContent !== preservedTitle) {
                    mobileHeaderTitle.textContent = preservedTitle;
                }
                
                // Reload archived contacts if in archived view
                const archivedTab = document.getElementById('chatArchivedTab');
                if (archivedTab && archivedTab.classList.contains('active')) {
                    await loadArchivedContacts(true);
                }
                // Check if archived tab should be shown/hidden
                await checkArchivedTabVisibility();
                
                // If this was the current chat, close it
                if (currentChatId && parseInt(currentChatId) === contextMenuChatId) {
                    // Close chat view
                    const chatEmptyState = document.getElementById('chatEmptyState');
                    const chatMessagesHeader = document.getElementById('chatMessagesHeader');
                    const chatMessagesContainer = document.getElementById('chatMessagesContainer');
                    const chatInputContainer = document.getElementById('chatInputContainer');
                    
                    if (chatEmptyState) chatEmptyState.style.display = 'flex';
                    if (chatMessagesHeader) chatMessagesHeader.style.display = 'none';
                    if (chatMessagesContainer) chatMessagesContainer.style.display = 'none';
                    if (chatInputContainer) chatInputContainer.style.display = 'none';
                    
                    currentChatId = null;
                    currentContactId = null;
                }
            } else {
                alert('Fehler beim Archivieren: ' + (data.message || 'Unbekannter Fehler'));
            }
        } catch (error) {
            console.error('Fehler beim Archivieren:', error);
            alert('Fehler beim Archivieren: ' + (error.message || 'Unbekannter Fehler'));
        }
    }
    
    // Show remove confirmation modal
    function showRemoveConfirmModal() {
        const modal = document.getElementById('chatRemoveConfirmModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }
    
    // Hide remove confirmation modal
    function hideRemoveConfirmModal() {
        const modal = document.getElementById('chatRemoveConfirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    // Handle remove chat (visual only, archives the chat)
    async function handleRemoveChat() {
        if (!contextMenuChatId) return;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const formData = new URLSearchParams();
            formData.append('chat_id', contextMenuChatId);
            formData.append('archive', '1'); // Archive instead of delete
            
            const response = await fetch(basePath + 'api/archive-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                // Preserve mobile header title if in contacts view
                const mobileHeaderTitle = document.querySelector('.chat-mobile-header-title');
                const preservedTitle = mobileHeaderTitle ? mobileHeaderTitle.textContent : null;
                
                // Remove from cache immediately for visual removal
                contactsCache = contactsCache.filter(c => c.chat_id !== contextMenuChatId);
                
                // Remove from DOM immediately
                const contactItem = document.querySelector(`[data-chat-id="${contextMenuChatId}"]`);
                if (contactItem) {
                    contactItem.remove();
                }
                
                // Reload contacts to reflect changes
                await loadContacts(true);
                
                // Restore mobile header title if it was changed
                if (preservedTitle && mobileHeaderTitle && mobileHeaderTitle.textContent !== preservedTitle) {
                    mobileHeaderTitle.textContent = preservedTitle;
                }
                
                // Reload archived contacts if in archived view
                const archivedTab = document.getElementById('chatArchivedTab');
                if (archivedTab && archivedTab.classList.contains('active')) {
                    await loadArchivedContacts(true);
                }
                // Check if archived tab should be shown/hidden
                await checkArchivedTabVisibility();
                
                // If this was the current chat, close it
                if (currentChatId && parseInt(currentChatId) === contextMenuChatId) {
                    // Close chat view
                    const chatEmptyState = document.getElementById('chatEmptyState');
                    const chatMessagesHeader = document.getElementById('chatMessagesHeader');
                    const chatMessagesContainer = document.getElementById('chatMessagesContainer');
                    const chatInputContainer = document.getElementById('chatInputContainer');
                    
                    if (chatEmptyState) chatEmptyState.style.display = 'flex';
                    if (chatMessagesHeader) chatMessagesHeader.style.display = 'none';
                    if (chatMessagesContainer) chatMessagesContainer.style.display = 'none';
                    if (chatInputContainer) chatInputContainer.style.display = 'none';
                    
                    currentChatId = null;
                    currentContactId = null;
                }
            } else {
                alert('Fehler beim Entfernen: ' + (data.message || 'Unbekannter Fehler'));
            }
        } catch (error) {
            console.error('Fehler beim Entfernen:', error);
            alert('Fehler beim Entfernen: ' + (error.message || 'Unbekannter Fehler'));
        }
    }
    
    // State for infinite scroll
    let newContactsState = {
        offset: 0,
        limit: 50,
        loading: false,
        hasMore: true,
        total: 0
    };
    
    let contactsState = {
        offset: 0,
        limit: 50,
        loading: false,
        hasMore: true,
        total: 0
    };
    
    let archivedContactsState = {
        offset: 0,
        limit: 50,
        loading: false,
        hasMore: true,
        total: 0
    };
    
    let archivedContactsCache = [];
    
    // Load new contacts (uncontacted users) with infinite scroll
    async function loadNewContacts(reset = false) {
        const newContactsList = document.getElementById('chatNewContactsList');
        if (!newContactsList) return;
        
        // Reset state if needed
        if (reset) {
            newContactsState.offset = 0;
            newContactsState.hasMore = true;
        newContactsList.innerHTML = '<div class="chat-loading">Lade Kontakte...</div>';
        }
        
        // Don't load if already loading or no more data
        if (newContactsState.loading || !newContactsState.hasMore) {
            return;
        }
        
        newContactsState.loading = true;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + `api/get-uncontacted-users.php?limit=${newContactsState.limit}&offset=${newContactsState.offset}`);
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                const contacts = data.contacts || [];
                newContactsState.total = data.total || 0;
                
                // Check if there are more contacts
                newContactsState.hasMore = (newContactsState.offset + contacts.length) < newContactsState.total;
                
                if (reset && contacts.length === 0) {
                    newContactsList.innerHTML = '<div class="chat-empty-contacts">Keine neuen Kontakte verfügbar</div>';
                    newContactsState.loading = false;
                    return;
                }
                
                // Remove loading indicator if exists
                const loadingIndicator = newContactsList.querySelector('.chat-loading-more');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
                
                // Get existing user IDs to avoid duplicates
                const existingUserIds = new Set();
                if (!reset) {
                    newContactsList.querySelectorAll('.chat-new-contact-item').forEach(item => {
                        const userId = item.dataset.userId ? parseInt(item.dataset.userId) : null;
                        if (userId) {
                            existingUserIds.add(userId);
                        }
                    });
                }
                
                // Filter out duplicates
                const uniqueContacts = contacts.filter(contact => {
                    const userId = parseInt(contact.user_id);
                    return !existingUserIds.has(userId);
                });
                
                // Append or replace contacts - don't set active in HTML, will be set by restore logic
                // Note: No status indicator for new contacts (only for existing contacts)
                const contactsHTML = uniqueContacts.map(contact => {
                    return `
                        <div class="chat-new-contact-item" data-user-id="${contact.user_id}" data-username="${escapeHtml(contact.username)}">
                            <div class="chat-new-contact-avatar-wrapper">
                                <img class="chat-new-contact-avatar" 
                                     src="${escapeHtml(contact.avatar || (typeof getBasePath === 'function' ? getBasePath() : '') + 'assets/images/profile-placeholder.svg')}" 
                                     alt="${escapeHtml(contact.name || contact.username || '')}">
                            </div>
                            <div class="chat-new-contact-info">
                                <h4 class="chat-new-contact-name">${escapeHtml(contact.name || contact.username || 'Unbekannt')}</h4>
                                <div class="chat-new-contact-status">Keine Nachrichten</div>
                            </div>
                            <button class="chat-new-contact-add-btn" data-user-id="${contact.user_id}" data-username="${escapeHtml(contact.username)}" title="Zu Kontakten hinzufügen">
                                👋
                            </button>
                        </div>
                    `;
                }).join('');
                
                if (reset) {
                    newContactsList.innerHTML = contactsHTML;
                } else {
                    // Append to existing list, remove loading indicator first
                    const existingHTML = newContactsList.innerHTML.replace('<div class="chat-loading-more">Lade weitere Kontakte...</div>', '');
                    newContactsList.innerHTML = existingHTML + contactsHTML;
                }
                
                // Update offset only with unique contacts
                newContactsState.offset += uniqueContacts.length;
                
                // Add click handlers to new items only
                newContactsList.querySelectorAll('.chat-new-contact-item').forEach(item => {
                    // Skip if already has handler
                    if (item.dataset.hasHandler === 'true') return;
                    item.dataset.hasHandler = 'true';
                    
                    // Add button click handler (separate from item click)
                    const addBtn = item.querySelector('.chat-new-contact-add-btn');
                    if (addBtn) {
                        addBtn.addEventListener('click', async function(e) {
                            e.stopPropagation();
                            e.preventDefault();
                            
                            const userId = parseInt(this.dataset.userId);
                            const username = this.dataset.username || 'Unbekannt';
                            
                            if (!userId || isNaN(userId)) {
                                console.error('Invalid userId:', this.dataset.userId);
                                return;
                            }
                            
                            // Add contact to contacts tab by creating a chat and sending a message
                            await addContactToContacts(userId, username, item);
                        });
                    }
                    
                    item.addEventListener('click', async function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        
                        const userId = parseInt(this.dataset.userId);
                        const username = this.dataset.username;
                        
                        // Validate inputs
                        if (!userId || isNaN(userId)) {
                            console.error('Invalid userId:', this.dataset.userId);
                            return;
                        }
                        
                        // Remove active from ALL contacts (both tabs) first
                        document.querySelectorAll('.chat-new-contact-item, .chat-contact-item').forEach(contactItem => {
                            contactItem.classList.remove('active');
                        });
                        
                        // Mark ONLY this contact as active
                        this.classList.add('active');
                        
                        // Always create or open chat - contact stays in "neue kontakte"
                        // The contact will only be removed from "neue kontakte" when a message is sent
                        // and will only appear in "kontakte" after at least one message exists
                        await createNewChat(userId, username, false);
                    });
                });
                
                // Restore active state for current contact if chat is open
                // First remove active from ALL contacts in BOTH tabs
                document.querySelectorAll('.chat-new-contact-item, .chat-contact-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Then set active on ONLY ONE item - the first match in neue kontakte
                if (currentContactId) {
                    let foundActive = false;
                    newContactsList.querySelectorAll('.chat-new-contact-item').forEach(item => {
                        const userId = parseInt(item.dataset.userId);
                        if (userId === currentContactId && !foundActive) {
                            item.classList.add('active');
                            foundActive = true;
                        }
                    });
                }
            } else {
                if (reset) {
                newContactsList.innerHTML = '<div class="chat-empty-contacts">Fehler beim Laden: ' + (data.message || 'Unbekannter Fehler') + '</div>';
                }
            }
        } catch (error) {
            console.error('Fehler beim Laden neuer Kontakte:', error);
            if (reset) {
            newContactsList.innerHTML = '<div class="chat-empty-contacts">Fehler beim Laden der Kontakte</div>';
        }
        } finally {
            newContactsState.loading = false;
        }
    }
    
    // Setup infinite scroll for new contacts
    function setupNewContactsScroll() {
        const newContactsList = document.getElementById('chatNewContactsList');
        if (!newContactsList) return;
        
        // Remove existing listener if any
        if (newContactsList._scrollHandler) {
            newContactsList.removeEventListener('scroll', newContactsList._scrollHandler);
        }
        
        newContactsList._scrollHandler = function() {
            // Check if scrolled near bottom (within 100px)
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100 && newContactsState.hasMore && !newContactsState.loading) {
                // Show loading indicator
                const loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'chat-loading-more';
                loadingIndicator.textContent = 'Lade weitere Kontakte...';
                this.appendChild(loadingIndicator);
                
                // Load more contacts
                loadNewContacts(false);
            }
        };
        
        newContactsList.addEventListener('scroll', newContactsList._scrollHandler);
    }
    
    // Search new contacts
    async function searchNewContacts(query) {
        const newContactsList = document.getElementById('chatNewContactsList');
        if (!newContactsList) return;
        
        if (query === '') {
            loadNewContacts(true);
            return;
        }
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + `api/search-chat-contacts.php?q=${encodeURIComponent(query)}`);
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                const contacts = data.contacts || [];
                
                if (contacts.length === 0) {
                    newContactsList.innerHTML = '<div class="chat-empty-contacts">Keine Kontakte gefunden</div>';
                    return;
                }
                
                newContactsList.innerHTML = contacts.map(contact => {
                    const status = contact.status || 'offline';
                    const statusClass = `chat-status-indicator chat-status-${status}`;
                    const statusTitle = status === 'online' ? 'Online' : status === 'away' ? 'Abwesend' : 'Offline';
                    return `
                        <div class="chat-new-contact-item" data-user-id="${contact.user_id}" data-username="${escapeHtml(contact.username)}">
                            <div class="chat-new-contact-avatar-wrapper">
                            <img class="chat-new-contact-avatar" 
                                 src="${escapeHtml(contact.avatar || (typeof getBasePath === 'function' ? getBasePath() : '') + 'assets/images/profile-placeholder.svg')}" 
                                 alt="${escapeHtml(contact.name || contact.username || '')}">
                                <span class="${statusClass}" title="${statusTitle}"></span>
                            </div>
                            <div class="chat-new-contact-info">
                                <h4 class="chat-new-contact-name">${escapeHtml(contact.name || contact.username || 'Unbekannt')}</h4>
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Add click handlers
                newContactsList.querySelectorAll('.chat-new-contact-item').forEach(item => {
                    // Skip if already has handler
                    if (item.dataset.hasHandler === 'true') return;
                    item.dataset.hasHandler = 'true';
                    
                    item.addEventListener('click', async function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        
                        const userId = parseInt(this.dataset.userId);
                        const username = this.dataset.username;
                        
                        // Validate inputs
                        if (!userId || isNaN(userId)) {
                            console.error('Invalid userId:', this.dataset.userId);
                            return;
                        }
                        
                        // Remove active from ALL contacts (both tabs) first
                        document.querySelectorAll('.chat-new-contact-item, .chat-contact-item').forEach(contactItem => {
                            contactItem.classList.remove('active');
                        });
                        
                        // Mark ONLY this contact as active
                        this.classList.add('active');
                        
                        // Always create or open chat - contact stays in "neue kontakte"
                        // The contact will only be removed from "neue kontakte" when a message is sent
                        // and will only appear in "kontakte" after at least one message exists
                        await createNewChat(userId, username, false);
                        
                        // Do NOT switch to contacts view or reload contacts
                        // The contact should stay in "neue kontakte" until a message is actually sent
                        // Only after sending a message will the contact appear in "kontakte" list
                        // This is handled by the sendMessage() function which calls loadNewContacts()
                    });
                });
                
                // Restore active state for current contact if chat is open
                // First remove active from ALL contacts in BOTH tabs
                document.querySelectorAll('.chat-new-contact-item, .chat-contact-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Then set active on ONLY ONE item - the first match in neue kontakte
                if (currentContactId) {
                    let foundActive = false;
                    newContactsList.querySelectorAll('.chat-new-contact-item').forEach(item => {
                        const userId = parseInt(item.dataset.userId);
                        if (userId === currentContactId && !foundActive) {
                            item.classList.add('active');
                            foundActive = true;
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Fehler bei der Suche:', error);
        }
    }
    
    // Load contacts with infinite scroll
    async function loadContacts(reset = false) {
        const contactsList = document.getElementById('chatContactsList');
        if (!contactsList) return;
        
        // Reset state if needed
        if (reset) {
            contactsState.offset = 0;
            contactsState.hasMore = true;
            contactsCache = [];
            contactsList.innerHTML = '<div class="chat-loading">Lade Kontakte...</div>';
        }
        
        // Don't load if already loading or no more data
        if (contactsState.loading || !contactsState.hasMore) {
            return;
        }
        
        contactsState.loading = true;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + `api/get-chat-contacts.php?limit=${contactsState.limit}&offset=${contactsState.offset}`);
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                const newContacts = data.contacts || [];
                contactsState.total = data.total || 0;
                
                // Check if there are more contacts
                contactsState.hasMore = (contactsState.offset + newContacts.length) < contactsState.total;
                
                // Store participant IDs for each contact and current user ID
                if (data.current_user_id) {
                    currentUserId = parseInt(data.current_user_id);
                }
                if (newContacts && Array.isArray(newContacts)) {
                    newContacts.forEach(contact => {
                        if (contact.participant_ids && Array.isArray(contact.participant_ids) && contact.participant_ids.length >= 2) {
                            const chatId = parseInt(contact.chat_id);
                            if (chatId) {
                                chatParticipants.set(chatId, contact.participant_ids);
                            }
                        }
                    });
                }
                
                // Get existing chat IDs to avoid duplicates when appending
                const existingChatIds = new Set();
                if (!reset) {
                    contactsCache.forEach(contact => {
                        const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
                        if (chatId) {
                            existingChatIds.add(chatId);
                        }
                    });
                }
                
                // Filter out duplicates
                const uniqueNewContacts = reset ? newContacts : newContacts.filter(contact => {
                    const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
                    return !chatId || !existingChatIds.has(chatId);
                });
                
                // Add to cache - also ensure no duplicates in cache itself
                if (reset) {
                    contactsCache = uniqueNewContacts;
                } else {
                    // Additional safety: filter out any duplicates that might already be in cache
                    const cacheChatIds = new Set();
                    contactsCache.forEach(c => {
                        const chatId = c.chat_id ? parseInt(c.chat_id) : null;
                        if (chatId) {
                            cacheChatIds.add(chatId);
                        }
                    });
                    
                    const trulyUniqueNewContacts = uniqueNewContacts.filter(contact => {
                        const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
                        return !chatId || !cacheChatIds.has(chatId);
                    });
                    contactsCache = [...contactsCache, ...trulyUniqueNewContacts];
                }
                
                // Update offset with the number of contacts from API (not filtered count)
                // This ensures pagination works correctly
                contactsState.offset += newContacts.length;
                
                await displayContacts(contactsCache, reset);
                // Check archived tab visibility after loading contacts
                await checkArchivedTabVisibility();
            } else {
                console.error('Fehler beim Laden der Kontakte:', data.message);
                if (reset) {
                    contactsList.innerHTML = '<div class="chat-empty-contacts">Fehler beim Laden der Kontakte</div>';
                }
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (reset) {
                contactsList.innerHTML = '<div class="chat-empty-contacts">Fehler beim Laden der Kontakte</div>';
            }
        } finally {
            contactsState.loading = false;
        }
    }
    
    // Setup infinite scroll for contacts
    function setupContactsScroll() {
        const contactsList = document.getElementById('chatContactsList');
        if (!contactsList) return;
        
        // Remove existing listener if any
        contactsList.removeEventListener('scroll', contactsList._scrollHandler);
        
        contactsList._scrollHandler = function() {
            // Check if scrolled near bottom (within 100px)
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            
            if (scrollHeight - scrollTop - clientHeight < 100 && contactsState.hasMore && !contactsState.loading) {
                // Show loading indicator
                const loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'chat-loading-more';
                loadingIndicator.textContent = 'Lade weitere Kontakte...';
                this.appendChild(loadingIndicator);
                
                // Load more contacts
                loadContacts(false); // Load more without reset
            }
        };
        
        contactsList.addEventListener('scroll', contactsList._scrollHandler);
    }
    
    // Load archived contacts
    async function loadArchivedContacts(reset = false) {
        const archivedList = document.getElementById('chatArchivedContactsList');
        if (!archivedList) return;
        
        // Reset state if needed
        if (reset) {
            archivedContactsState.offset = 0;
            archivedContactsState.hasMore = true;
            archivedContactsCache = [];
            archivedList.innerHTML = '<div class="chat-loading">Lade archivierte Kontakte...</div>';
        }
        
        // Don't load if already loading or no more data
        if (archivedContactsState.loading || !archivedContactsState.hasMore) {
            return;
        }
        
        archivedContactsState.loading = true;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + `api/get-archived-contacts.php?limit=${archivedContactsState.limit}&offset=${archivedContactsState.offset}`);
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success) {
                const newContacts = data.contacts || [];
                archivedContactsState.total = data.total || 0;
                
                // Check if there are more contacts
                archivedContactsState.hasMore = (archivedContactsState.offset + newContacts.length) < archivedContactsState.total;
                
                // Store participant IDs for each contact and current user ID
                if (data.current_user_id) {
                    currentUserId = parseInt(data.current_user_id);
                }
                if (newContacts && Array.isArray(newContacts)) {
                    newContacts.forEach(contact => {
                        if (contact.participant_ids && Array.isArray(contact.participant_ids) && contact.participant_ids.length >= 2) {
                            const chatId = parseInt(contact.chat_id);
                            if (chatId) {
                                chatParticipants.set(chatId, contact.participant_ids);
                            }
                        }
                    });
                }
                
                // Get existing chat IDs to avoid duplicates when appending
                const existingChatIds = new Set();
                if (!reset) {
                    archivedContactsCache.forEach(contact => {
                        const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
                        if (chatId) {
                            existingChatIds.add(chatId);
                        }
                    });
                }
                
                // Filter out duplicates
                const uniqueNewContacts = reset ? newContacts : newContacts.filter(contact => {
                    const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
                    return !chatId || !existingChatIds.has(chatId);
                });
                
                // Add to cache
                if (reset) {
                    archivedContactsCache = uniqueNewContacts;
                } else {
                    const cacheChatIds = new Set();
                    archivedContactsCache.forEach(c => {
                        const chatId = c.chat_id ? parseInt(c.chat_id) : null;
                        if (chatId) {
                            cacheChatIds.add(chatId);
                        }
                    });
                    
                    const trulyUniqueNewContacts = uniqueNewContacts.filter(contact => {
                        const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
                        return !chatId || !cacheChatIds.has(chatId);
                    });
                    archivedContactsCache = [...archivedContactsCache, ...trulyUniqueNewContacts];
                }
                
                // Update offset
                archivedContactsState.offset += newContacts.length;
                
                await displayArchivedContacts(archivedContactsCache, reset);
            } else {
                console.error('Fehler beim Laden der archivierten Kontakte:', data.message);
                if (reset) {
                    archivedList.innerHTML = '<div class="chat-empty-contacts">Fehler beim Laden der archivierten Kontakte</div>';
                }
            }
        } catch (error) {
            console.error('Fehler:', error);
            if (reset) {
                archivedList.innerHTML = '<div class="chat-empty-contacts">Fehler beim Laden der archivierten Kontakte</div>';
            }
        } finally {
            archivedContactsState.loading = false;
        }
    }
    
    // Display archived contacts (same structure as displayContacts)
    async function displayArchivedContacts(contacts, reset = false) {
        const archivedList = document.getElementById('chatArchivedContactsList');
        if (!archivedList) return;
        
        if (reset && contacts.length === 0) {
            archivedList.innerHTML = '<div class="chat-empty-contacts">Keine archivierten Kontakte gefunden</div>';
            return;
        }
        
        // Remove loading indicator if exists
        const loadingIndicator = archivedList.querySelector('.chat-loading-more');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }
        
        // Filter out duplicates by chat_id (same as displayContacts)
        const seenChatIds = new Set();
        const uniqueContacts = contacts.filter(contact => {
            const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
            if (!chatId) return true; // Include contacts without chat_id
            if (seenChatIds.has(chatId)) {
                return false; // Duplicate, skip
            }
            seenChatIds.add(chatId);
            return true;
        });
        
        // Sort: favorites first, then by last_message_at (same as displayContacts)
        uniqueContacts.sort((a, b) => {
            const aIsFavorite = a.is_favorite || false;
            const bIsFavorite = b.is_favorite || false;
            if (aIsFavorite && !bIsFavorite) return -1;
            if (!aIsFavorite && bIsFavorite) return 1;
            
            const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
            const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
            return bTime - aTime;
        });
        
        // Process contacts with async decryption (same as displayContacts)
        const contactPromises = uniqueContacts.map(async (contact) => {
            const unreadBadge = contact.unread_count > 0 
                ? `<span class="chat-contact-badge">${contact.unread_count}</span>` 
                : '';
            
            const lastMessageTime = contact.last_message_at 
                ? formatTime(contact.last_message_at) 
                : '';
            
            // No decryption - use message as-is
            let lastMessageText = contact.last_message || '';
            
            return { contact, unreadBadge, lastMessageTime, lastMessageText };
        });
        
        const processedContacts = await Promise.all(contactPromises);
        
        // If no contacts to add and not resetting, don't modify the DOM
        if (processedContacts.length === 0 && !reset) {
            // Remove loading indicator if exists
            const loadingEl = contactsList.querySelector('.chat-loading');
            if (loadingEl) {
                loadingEl.remove();
            }
            return;
        }
        
        // Build HTML using same structure as displayContacts
        const contactsHTML = processedContacts.map(({ contact, unreadBadge, lastMessageTime, lastMessageText }) => {
            // Check if last message has files (same logic as displayContacts)
            let hasFiles = false;
            let fileCount = 0;
            let fileType = null;
            let isGif = false;
            let hasText = false;
            
            if (lastMessageText && (lastMessageText.startsWith('[') || lastMessageText.startsWith('{'))) {
                try {
                    const parsed = JSON.parse(lastMessageText);
                    if (Array.isArray(parsed)) {
                        hasFiles = true;
                        fileCount = parsed.length;
                        if (parsed.length > 0) {
                            const allGifs = parsed.every(img => img.mime && img.mime === 'image/gif');
                            if (allGifs) {
                                isGif = true;
                                fileType = 'gif';
                            } else {
                                fileType = 'image';
                            }
                        } else {
                            fileType = 'image';
                        }
                        if (parsed.text && parsed.text.trim()) {
                            hasText = true;
                            lastMessageText = parsed.text;
                        } else {
                            lastMessageText = '';
                        }
                    } else if (parsed && typeof parsed === 'object' && parsed.images) {
                        hasFiles = true;
                        fileCount = Array.isArray(parsed.images) ? parsed.images.length : 0;
                        if (parsed.images.length > 0 && Array.isArray(parsed.images)) {
                            const allGifs = parsed.images.every(img => img.mime && img.mime === 'image/gif');
                            if (allGifs) {
                                isGif = true;
                                fileType = 'gif';
                            } else {
                                fileType = 'image';
                            }
                        } else {
                            fileType = 'image';
                        }
                        if (parsed.text && parsed.text.trim()) {
                            hasText = true;
                            lastMessageText = parsed.text;
                        } else {
                            lastMessageText = '';
                        }
                    }
                } catch (e) {
                    hasText = true;
                }
            } else if (contact.last_message_file_type) {
                hasFiles = true;
                fileType = contact.last_message_file_type;
                
                if (contact.last_message_file_type === 'multiple' && contact.last_message_file_path) {
                    try {
                        const parsed = JSON.parse(contact.last_message_file_path);
                        if (Array.isArray(parsed)) {
                            fileCount = parsed.length;
                            const allGifs = parsed.every(file => 
                                (file.path && file.path.toLowerCase().endsWith('.gif')) || 
                                (file.mime && file.mime === 'image/gif')
                            );
                            if (allGifs) {
                                isGif = true;
                                fileType = 'gif';
                            }
                        } else {
                            fileCount = 2;
                        }
                    } catch (e) {
                        fileCount = 2;
                    }
                } else {
                    fileCount = 1;
                }
                
                if (!isGif && contact.last_message_file_path) {
                    const lowerPath = contact.last_message_file_path.toLowerCase();
                    if (lowerPath.endsWith('.gif')) {
                        isGif = true;
                        fileType = 'gif';
                    }
                }
                
                if (lastMessageText && lastMessageText.trim() && !lastMessageText.startsWith('data:') && !lastMessageText.startsWith('[') && !lastMessageText.startsWith('{')) {
                    hasText = true;
                } else {
                    lastMessageText = '';
                }
            } else if (lastMessageText && lastMessageText.trim() && !lastMessageText.startsWith('data:') && !lastMessageText.startsWith('[') && !lastMessageText.startsWith('{')) {
                hasText = true;
            }
            
            // Build final message preview (same as displayContacts)
            let displayText = '';
            let showPrefix = true;
            
            if (hasFiles && !hasText) {
                showPrefix = false;
                let fileTypeLabel = '';
                let iconSvg = '';
                
                if (isGif) {
                    fileTypeLabel = fileCount > 1 ? 'GIFs' : 'GIF';
                    iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'image' || fileType === 'multiple') {
                    fileTypeLabel = (fileType === 'multiple' || fileCount > 1) ? 'Bilder' : 'Bild';
                    iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'video') {
                    fileTypeLabel = fileCount > 1 ? 'Videos' : 'Video';
                    iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>';
                }
                
                displayText = iconSvg + fileTypeLabel;
            } else if (hasFiles && hasText) {
                let fileIndicator = '';
                if (isGif) {
                    fileIndicator = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'image' || fileType === 'multiple') {
                    fileIndicator = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'video') {
                    fileIndicator = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>';
                }
                displayText = fileIndicator + escapeHtml(lastMessageText);
                showPrefix = true;
            } else if (hasText && lastMessageText && lastMessageText.trim()) {
                displayText = escapeHtml(lastMessageText);
                showPrefix = true;
            } else {
                displayText = 'Keine Nachrichten';
                showPrefix = false;
            }
            
            // Add prefix based on who sent the last message (same as displayContacts)
            let lastMessagePreview = displayText;
            if (showPrefix && (contact.last_message || contact.last_message_file_type)) {
                if (contact.is_last_message_from_me) {
                    lastMessagePreview = '<strong>Du:</strong> ' + displayText;
                } else {
                    const contactName = (contact.name || contact.username || 'Unbekannt').split(' ')[0];
                    lastMessagePreview = '<strong>' + escapeHtml(contactName) + ':</strong> ' + displayText;
                }
            }
            
            const status = contact.status || 'offline';
            const statusClass = `chat-status-indicator chat-status-${status}`;
            const statusTitle = status === 'online' ? 'Online' : status === 'away' ? 'Abwesend' : 'Offline';
            
            const isFavorite = contact.is_favorite || false;
            const favoriteClass = isFavorite ? 'favorite' : '';
            const favoriteStar = isFavorite ? '<svg class="chat-contact-favorite-star" width="18" height="18" viewBox="0 0 24 24" fill="#ffc107" stroke="#ffc107" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>' : '';
            
            return `
                <div class="chat-contact-item ${favoriteClass}" 
                     data-chat-id="${contact.chat_id || ''}" 
                     data-user-id="${contact.user_id}"
                     data-username="${escapeHtml(contact.username || '')}"
                     data-is-favorite="${isFavorite ? '1' : '0'}"
                     data-is-archived="1">
                    <div class="chat-contact-avatar-wrapper">
                        <img class="chat-contact-avatar" 
                             src="${escapeHtml(contact.avatar || (typeof getBasePath === 'function' ? getBasePath() : '') + 'assets/images/profile-placeholder.svg')}" 
                             alt="${escapeHtml(contact.name || contact.username || '')}">
                        <span class="${statusClass}" title="${statusTitle}"></span>
                    </div>
                    <div class="chat-contact-info">
                        <div class="chat-contact-header">
                            <h4 class="chat-contact-name">${escapeHtml(contact.name || contact.username || 'Unbekannt')}</h4>
                            <div class="chat-contact-header-right">
                                ${lastMessageTime ? `<span class="chat-contact-time">${lastMessageTime}</span>` : ''}
                                ${favoriteStar}
                            </div>
                        </div>
                        <div class="chat-contact-preview">${lastMessagePreview}</div>
                    </div>
                    ${unreadBadge}
                </div>
            `;
        }).join('');
        
        // Append or replace HTML (same as displayContacts)
        if (reset) {
            archivedList.innerHTML = contactsHTML;
        } else {
            if (contactsHTML) {
                const existingHTML = archivedList.innerHTML.replace('<div class="chat-loading-more">Lade weitere Kontakte...</div>', '');
                archivedList.innerHTML = existingHTML + contactsHTML;
            }
        }
        
        // Add click handlers to items (same as displayContacts)
        archivedList.querySelectorAll('.chat-contact-item').forEach(item => {
            if (item.dataset.hasHandler === 'true') return;
            item.dataset.hasHandler = 'true';
            
            // Right-click handler for context menu (desktop)
            item.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const chatIdStr = this.dataset.chatId;
                const chatId = chatIdStr && chatIdStr !== 'null' && chatIdStr !== 'undefined' ? parseInt(chatIdStr) : null;
                
                if (chatId && !isNaN(chatId) && chatId > 0) {
                    setTimeout(() => {
                        showContactContextMenu(e, chatId, this);
                    }, 10);
                }
            });
            
            // Long press handler for context menu (mobile)
            let longPressTimer = null;
            let longPressStartX = 0;
            let longPressStartY = 0;
            let longPressTriggered = false;
            
            item.addEventListener('touchstart', function(e) {
                const chatIdStr = this.dataset.chatId;
                const chatId = chatIdStr && chatIdStr !== 'null' && chatIdStr !== 'undefined' ? parseInt(chatIdStr) : null;
                
                if (chatId && !isNaN(chatId) && chatId > 0) {
                    longPressTriggered = false;
                    const touch = e.touches[0];
                    longPressStartX = touch.clientX;
                    longPressStartY = touch.clientY;
                    
                    longPressTimer = setTimeout(() => {
                        longPressTriggered = true;
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const syntheticEvent = {
                            clientX: touch.clientX,
                            clientY: touch.clientY,
                            preventDefault: () => {},
                            stopPropagation: () => {}
                        };
                        
                        showContactContextMenu(syntheticEvent, chatId, this);
                        longPressTimer = null;
                    }, 500);
                }
            });
            
            item.addEventListener('touchend', function(e) {
                if (longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
            });
            
            item.addEventListener('touchmove', function(e) {
                if (longPressTimer) {
                    const touch = e.touches[0];
                    const deltaX = Math.abs(touch.clientX - longPressStartX);
                    const deltaY = Math.abs(touch.clientY - longPressStartY);
                    
                    if (deltaX > 10 || deltaY > 10) {
                        clearTimeout(longPressTimer);
                        longPressTimer = null;
                    }
                }
            });
            
            item.addEventListener('click', async function(e) {
                const contextMenu = document.getElementById('chatContextMenu');
                if (contextMenu && contextMenu.contains(e.target)) {
                    return;
                }
                
                e.stopPropagation();
                e.preventDefault();
                
                hideContextMenu();
                
                const chatIdStr = this.dataset.chatId;
                const chatId = chatIdStr && chatIdStr !== 'null' && chatIdStr !== 'undefined' ? parseInt(chatIdStr) : null;
                const userId = parseInt(this.dataset.userId);
                const username = this.dataset.username || 'Unbekannt';
                
                if (chatId && !isNaN(chatId) && chatId > 0) {
                    await openChat(chatId, userId, username);
                }
            });
        });
        
        // Setup scroll handler if not already set
        if (!archivedList._scrollHandler) {
            archivedList._scrollHandler = function() {
                const scrollTop = this.scrollTop;
                const scrollHeight = this.scrollHeight;
                const clientHeight = this.clientHeight;
                
                if (scrollHeight - scrollTop - clientHeight < 100 && archivedContactsState.hasMore && !archivedContactsState.loading) {
                    const loadingIndicator = document.createElement('div');
                    loadingIndicator.className = 'chat-loading-more';
                    loadingIndicator.textContent = 'Lade weitere Kontakte...';
                    this.appendChild(loadingIndicator);
                    
                    loadArchivedContacts(false);
                }
            };
            
            archivedList.addEventListener('scroll', archivedList._scrollHandler);
        }
    }
    
    // Check if archived tab should be visible
    async function checkArchivedTabVisibility() {
        const archivedTab = document.getElementById('chatArchivedTab');
        if (!archivedTab) return;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + 'api/get-archived-contacts.php?limit=1&offset=0');
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error! status:', response.status, 'Response:', errorText.substring(0, 200));
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's JSON
            const responseText = await response.text();
            
            // Check if response is JSON by trying to parse it
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response. Response text:', responseText.substring(0, 500));
                throw new Error('Server returned invalid JSON response');
            }
            
            if (data.success && data.total > 0) {
                archivedTab.style.display = 'flex';
            } else {
                archivedTab.style.display = 'none';
                // If archived tab was active, switch to contacts
                if (archivedTab.classList.contains('active')) {
                    switchView('contacts');
                }
            }
        } catch (error) {
            console.error('Fehler beim Prüfen archivierter Kontakte:', error);
        }
    }
    
    // Display contacts
    async function displayContacts(contacts, reset = false) {
        const contactsList = document.getElementById('chatContactsList');
        if (!contactsList) return;
        
        if (reset && contacts.length === 0) {
            contactsList.innerHTML = '<div class="chat-empty-contacts">Keine Kontakte gefunden</div>';
            return;
        }
        
        // Remove loading indicators if exists
        const loadingIndicator = contactsList.querySelector('.chat-loading-more');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }
        const loadingElement = contactsList.querySelector('.chat-loading');
        if (loadingElement) {
            loadingElement.remove();
        }
        
        // CRITICAL: Filter out duplicates from contacts array by chat_id
        const seenChatIds = new Set();
        const uniqueContacts = contacts.filter(contact => {
            const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
            if (!chatId) return true; // Include contacts without chat_id
            if (seenChatIds.has(chatId)) {
                return false; // Duplicate, skip
            }
            seenChatIds.add(chatId);
            return true;
        });
        
        // Sort all contacts: favorites first, then by last message time
        // This ensures favorites are always at the top, even when resetting
        uniqueContacts.sort((a, b) => {
            // Favorites first
            const aIsFavorite = a.is_favorite || false;
            const bIsFavorite = b.is_favorite || false;
            if (aIsFavorite && !bIsFavorite) return -1;
            if (!aIsFavorite && bIsFavorite) return 1;
            
            // Then by last message time
            const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
            const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
            return bTime - aTime;
        });
        
        // If appending (reset=false), also check existing DOM elements to avoid duplicates
        const existingChatIdsInDOM = new Set();
        const existingUserIdsInDOM = new Set();
        if (!reset) {
            contactsList.querySelectorAll('.chat-contact-item').forEach(item => {
                const chatId = item.dataset.chatId ? parseInt(item.dataset.chatId) : null;
                const userId = item.dataset.userId ? parseInt(item.dataset.userId) : null;
                if (chatId) {
                    existingChatIdsInDOM.add(chatId);
                }
                if (userId) {
                    existingUserIdsInDOM.add(userId);
                }
            });
        }
        
        // Filter out contacts that are already in the DOM
        // Check both chat_id and user_id to be thorough
        const contactsToAdd = reset ? uniqueContacts : uniqueContacts.filter(contact => {
            const chatId = contact.chat_id ? parseInt(contact.chat_id) : null;
            const userId = contact.user_id ? parseInt(contact.user_id) : null;
            // Skip if chat_id exists in DOM, or if user_id exists and chat_id is missing
            if (chatId && existingChatIdsInDOM.has(chatId)) {
                return false;
            }
            if (!chatId && userId && existingUserIdsInDOM.has(userId)) {
                return false;
            }
            return true;
        });
        
        // Process contacts with async decryption
        const contactPromises = contactsToAdd.map(async (contact) => {
            const unreadBadge = contact.unread_count > 0 
                ? `<span class="chat-contact-badge">${contact.unread_count}</span>` 
                : '';
            
            const lastMessageTime = contact.last_message_at 
                ? formatTime(contact.last_message_at) 
                : '';
            
            // Decrypt last message if encrypted
            let lastMessageText = contact.last_message || '';
            if (contact.last_message && contact.last_message_encrypted) {
                const chatId = parseInt(contact.chat_id);
                // Try to get participant IDs from map first, fallback to contact object
                let participantIds = chatId ? chatParticipants.get(chatId) : null;
                if (!participantIds && contact.participant_ids && Array.isArray(contact.participant_ids)) {
                    participantIds = contact.participant_ids;
                    // Store in map for future use
                    if (chatId && participantIds.length >= 2) {
                        chatParticipants.set(chatId, participantIds);
                    }
                }
                // No decryption - return message as-is
                lastMessageText = contact.last_message || '';
            }
            
            return { contact, unreadBadge, lastMessageTime, lastMessageText };
        });
        
        const processedContacts = await Promise.all(contactPromises);
        
        // If no contacts to add and not resetting, don't modify the DOM
        if (processedContacts.length === 0 && !reset) {
            return;
        }
        
        const contactsHTML = processedContacts.map(({ contact, unreadBadge, lastMessageTime, lastMessageText }) => {
            
            // Check if last message has files
            let hasFiles = false;
            let fileCount = 0;
            let fileType = null;
            let isGif = false;
            let hasText = false;
            
            // Check if message contains JSON with image data
            if (lastMessageText && (lastMessageText.startsWith('[') || lastMessageText.startsWith('{'))) {
                try {
                    const parsed = JSON.parse(lastMessageText);
                    if (Array.isArray(parsed)) {
                        hasFiles = true;
                        fileCount = parsed.length;
                        // Check if all are GIFs
                        if (parsed.length > 0) {
                            const allGifs = parsed.every(img => img.mime && img.mime === 'image/gif');
                            if (allGifs) {
                                isGif = true;
                                fileType = 'gif';
                            } else {
                                fileType = 'image';
                            }
                        } else {
                            fileType = 'image';
                        }
                        // Check if there's text in the object
                        if (parsed.text && parsed.text.trim()) {
                            hasText = true;
                            lastMessageText = parsed.text;
                        } else {
                            lastMessageText = '';
                        }
                    } else if (parsed && typeof parsed === 'object' && parsed.images) {
                        hasFiles = true;
                        fileCount = Array.isArray(parsed.images) ? parsed.images.length : 0;
                        // Check if all are GIFs
                        if (parsed.images.length > 0 && Array.isArray(parsed.images)) {
                            const allGifs = parsed.images.every(img => img.mime && img.mime === 'image/gif');
                            if (allGifs) {
                                isGif = true;
                                fileType = 'gif';
                            } else {
                                fileType = 'image';
                            }
                        } else {
                            fileType = 'image';
                        }
                        if (parsed.text && parsed.text.trim()) {
                            hasText = true;
                            lastMessageText = parsed.text;
                        } else {
                            lastMessageText = '';
                        }
                    }
                } catch (e) {
                    // Not JSON, treat as text
                    hasText = true;
                }
            } else if (contact.last_message_file_type) {
                hasFiles = true;
                fileType = contact.last_message_file_type;
                
                // Try to determine file count from file_path if it's JSON (multiple)
                if (contact.last_message_file_type === 'multiple' && contact.last_message_file_path) {
                    try {
                        const parsed = JSON.parse(contact.last_message_file_path);
                        if (Array.isArray(parsed)) {
                            fileCount = parsed.length;
                            // Check if all are GIFs
                            const allGifs = parsed.every(file => 
                                (file.path && file.path.toLowerCase().endsWith('.gif')) || 
                                (file.mime && file.mime === 'image/gif')
                            );
                            if (allGifs) {
                                isGif = true;
                                fileType = 'gif';
                            }
                        } else {
                            fileCount = 2; // Approximate
                        }
                    } catch (e) {
                        fileCount = 2; // Approximate
                    }
                } else {
                    fileCount = 1;
                }
                
                // Check if it's a GIF by looking at file_path
                if (!isGif && contact.last_message_file_path) {
                    const lowerPath = contact.last_message_file_path.toLowerCase();
                    if (lowerPath.endsWith('.gif')) {
                        isGif = true;
                        fileType = 'gif';
                    }
                }
                
                // Check if there's also text
                if (lastMessageText && lastMessageText.trim() && !lastMessageText.startsWith('data:') && !lastMessageText.startsWith('[') && !lastMessageText.startsWith('{')) {
                    hasText = true;
                } else {
                    lastMessageText = '';
                }
            } else if (lastMessageText && lastMessageText.trim() && !lastMessageText.startsWith('data:') && !lastMessageText.startsWith('[') && !lastMessageText.startsWith('{')) {
                hasText = true;
            }
            
            // Build final message preview
            let displayText = '';
            let showPrefix = true;
            
            if (hasFiles && !hasText) {
                // Only files, no text - show icon and file type
                showPrefix = false;
                
                let fileTypeLabel = '';
                let iconSvg = '';
                
                if (isGif) {
                    fileTypeLabel = fileCount > 1 ? 'GIFs' : 'GIF';
                    iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'image' || fileType === 'multiple') {
                    // For multiple, show "Bilder" if count > 1, otherwise "Bild"
                    fileTypeLabel = (fileType === 'multiple' || fileCount > 1) ? 'Bilder' : 'Bild';
                    iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'video') {
                    fileTypeLabel = fileCount > 1 ? 'Videos' : 'Video';
                    iconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>';
                }
                
                displayText = iconSvg + fileTypeLabel;
            } else if (hasFiles && hasText) {
                // Files and text - show text with file indicator
                let fileIndicator = '';
                if (isGif) {
                    fileIndicator = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'image' || fileType === 'multiple') {
                    fileIndicator = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                } else if (fileType === 'video') {
                    fileIndicator = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>';
                }
                displayText = fileIndicator + escapeHtml(lastMessageText);
                showPrefix = true;
            } else if (hasText && lastMessageText && lastMessageText.trim()) {
                displayText = escapeHtml(lastMessageText);
                showPrefix = true;
            } else {
                displayText = 'Keine Nachrichten';
                showPrefix = false;
            }
            
            // Add prefix based on who sent the last message (only if not file-only)
            let lastMessagePreview = displayText;
            if (showPrefix && (contact.last_message || contact.last_message_file_type)) {
                if (contact.is_last_message_from_me) {
                    // Last message from current user
                    lastMessagePreview = '<strong>Du:</strong> ' + displayText;
                } else {
                    // Last message from other user - show their name
                    const contactName = (contact.name || contact.username || 'Unbekannt').split(' ')[0]; // Use first name only
                    lastMessagePreview = '<strong>' + escapeHtml(contactName) + ':</strong> ' + displayText;
                }
            }
            
            // Don't set active in HTML - will be set by restore logic to ensure only one is active
            const status = contact.status || 'offline';
            const statusClass = `chat-status-indicator chat-status-${status}`;
            const statusTitle = status === 'online' ? 'Online' : status === 'away' ? 'Abwesend' : 'Offline';
            
            const isFavorite = contact.is_favorite || false;
            const favoriteClass = isFavorite ? 'favorite' : '';
            const favoriteStar = isFavorite ? '<svg class="chat-contact-favorite-star" width="18" height="18" viewBox="0 0 24 24" fill="#ffc107" stroke="#ffc107" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>' : '';
            
            return `
                <div class="chat-contact-item ${favoriteClass}" 
                     data-chat-id="${contact.chat_id || ''}" 
                     data-user-id="${contact.user_id}"
                     data-username="${escapeHtml(contact.username || '')}"
                     data-is-favorite="${isFavorite ? '1' : '0'}">
                    <div class="chat-contact-avatar-wrapper">
                        <img class="chat-contact-avatar" 
                             src="${escapeHtml(contact.avatar || (typeof getBasePath === 'function' ? getBasePath() : '') + 'assets/images/profile-placeholder.svg')}" 
                             alt="${escapeHtml(contact.name || contact.username || '')}">
                        <span class="${statusClass}" title="${statusTitle}"></span>
                    </div>
                    <div class="chat-contact-info">
                        <div class="chat-contact-header">
                            <h4 class="chat-contact-name">${escapeHtml(contact.name || contact.username || 'Unbekannt')}</h4>
                            <div class="chat-contact-header-right">
                                ${lastMessageTime ? `<span class="chat-contact-time">${lastMessageTime}</span>` : ''}
                                ${favoriteStar}
                            </div>
                        </div>
                        <div class="chat-contact-preview">${lastMessagePreview}</div>
                    </div>
                    ${unreadBadge}
                </div>
            `;
        }).join('');
        
        // Remove loading indicator before displaying (use different variable name to avoid conflict)
        const loadingEl2 = contactsList.querySelector('.chat-loading');
        if (loadingEl2) {
            loadingEl2.remove();
        }
        
        // If no contacts to add and not resetting, don't modify the DOM
        if (processedContacts.length === 0 && !reset) {
            return;
        }
        
        // Append or replace HTML
        if (reset) {
            // Complete replacement - clear everything first
            // This removes all old elements and their handlers
            if (contactsHTML && contactsHTML.trim() !== '') {
                contactsList.innerHTML = contactsHTML;
            } else {
                // If no contacts, show empty message
                contactsList.innerHTML = '<div class="chat-empty-contacts">Keine Kontakte gefunden</div>';
            }
        } else {
            // Append to existing list, but only if we have contacts to add
            if (contactsHTML && contactsHTML.trim() !== '') {
                // Remove any loading indicators
                let existingHTML = contactsList.innerHTML;
                existingHTML = existingHTML.replace('<div class="chat-loading-more">Lade weitere Kontakte...</div>', '');
                existingHTML = existingHTML.replace('<div class="chat-loading">Lade Kontakte...</div>', '');
                contactsList.innerHTML = existingHTML + contactsHTML;
            }
        }
        
        // Add click handlers to items
        // When innerHTML is replaced (reset=true), all elements are new
        // When appending (reset=false), only new elements need handlers
        contactsList.querySelectorAll('.chat-contact-item').forEach(item => {
            // Skip if already has handler (for append mode)
            if (item.dataset.hasHandler === 'true') return;
            item.dataset.hasHandler = 'true';
            
            // Right-click handler for context menu (desktop)
            item.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const chatIdStr = this.dataset.chatId;
                const chatId = chatIdStr && chatIdStr !== 'null' && chatIdStr !== 'undefined' ? parseInt(chatIdStr) : null;
                
                // Only show context menu for existing contacts with chat_id
                if (chatId && !isNaN(chatId) && chatId > 0) {
                    // Small delay to ensure any previous click events are processed
                    setTimeout(() => {
                        showContactContextMenu(e, chatId, this);
                    }, 10);
                }
            });
            
            // Long press handler for context menu (mobile)
            let longPressTimer = null;
            let longPressStartX = 0;
            let longPressStartY = 0;
            let longPressTriggered = false;
            
            item.addEventListener('touchstart', function(e) {
                const chatIdStr = this.dataset.chatId;
                const chatId = chatIdStr && chatIdStr !== 'null' && chatIdStr !== 'undefined' ? parseInt(chatIdStr) : null;
                
                // Only enable long press for existing contacts with chat_id
                if (chatId && !isNaN(chatId) && chatId > 0) {
                    longPressTriggered = false;
                    const touch = e.touches[0];
                    longPressStartX = touch.clientX;
                    longPressStartY = touch.clientY;
                    
                    longPressTimer = setTimeout(() => {
                        longPressTriggered = true;
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Create a synthetic event for positioning
                        const syntheticEvent = {
                            clientX: touch.clientX,
                            clientY: touch.clientY,
                            preventDefault: () => {},
                            stopPropagation: () => {}
                        };
                        
                        showContactContextMenu(syntheticEvent, chatId, this);
                        longPressTimer = null;
                    }, 500); // 500ms long press
                }
            });
            
            item.addEventListener('touchend', function(e) {
                if (longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
            });
            
            item.addEventListener('touchmove', function(e) {
                if (longPressTimer) {
                    const touch = e.touches[0];
                    const deltaX = Math.abs(touch.clientX - longPressStartX);
                    const deltaY = Math.abs(touch.clientY - longPressStartY);
                    
                    // Cancel long press if user moved too much
                    if (deltaX > 10 || deltaY > 10) {
                        clearTimeout(longPressTimer);
                        longPressTimer = null;
                    }
                }
            });
            
            item.addEventListener('click', async function(e) {
                // Prevent click if long press was triggered
                if (longPressTriggered) {
                    e.preventDefault();
                    e.stopPropagation();
                    longPressTriggered = false;
                    return;
                }
                e.stopPropagation();
                e.preventDefault();
                
                // Hide context menu if visible
                hideContextMenu();
                
                const chatIdStr = this.dataset.chatId;
                const chatId = chatIdStr && chatIdStr !== 'null' && chatIdStr !== 'undefined' ? parseInt(chatIdStr) : null;
                const userId = parseInt(this.dataset.userId);
                const username = this.dataset.username || 'Unbekannt';
                
                // Validate inputs
                if (!userId || isNaN(userId)) {
                    console.error('Invalid userId:', this.dataset.userId);
                    return;
                }
                
                // Remove active from ALL contacts (both tabs) first
                document.querySelectorAll('.chat-new-contact-item, .chat-contact-item').forEach(contactItem => {
                    contactItem.classList.remove('active');
                });
                
                // Mark ONLY this contact as active
                this.classList.add('active');
                
                // Always open/load the chat, even if chatId exists
                // This ensures the chat is properly loaded when switching between contacts
                if (chatId && !isNaN(chatId) && chatId > 0) {
                    // Chat exists - open it
                    await openChat(chatId, userId, username);
                } else {
                    // No chat yet - create new chat
                    await createNewChat(userId, username);
                }
            });
        });
        
        // Restore active state for current contact if chat is open
        // First remove active from ALL contacts in BOTH tabs
        document.querySelectorAll('.chat-new-contact-item, .chat-contact-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Then set active on ONLY ONE item - the first match in kontakte
        if (currentChatId || currentContactId) {
            let foundActive = false;
            contactsList.querySelectorAll('.chat-contact-item').forEach(item => {
                const itemChatId = item.dataset.chatId ? parseInt(item.dataset.chatId) : null;
                const itemUserId = item.dataset.userId ? parseInt(item.dataset.userId) : null;
                const shouldBeActive = (currentChatId && itemChatId === currentChatId) || 
                                      (currentContactId && itemUserId === currentContactId);
                
                if (shouldBeActive && !foundActive) {
                    item.classList.add('active');
                    foundActive = true;
                }
            });
        }
    }
    
    // Abort all ongoing requests when switching chats
    function abortAllChatRequests() {
        if (currentLoadMessagesAbortController) {
            currentLoadMessagesAbortController.abort();
            currentLoadMessagesAbortController = null;
        }
        if (currentCreateChatAbortController) {
            currentCreateChatAbortController.abort();
            currentCreateChatAbortController = null;
        }
        if (currentMarkAsReadAbortController) {
            currentMarkAsReadAbortController.abort();
            currentMarkAsReadAbortController = null;
        }
        if (currentCheckMessagesAbortController) {
            currentCheckMessagesAbortController.abort();
            currentCheckMessagesAbortController = null;
        }
    }
    
    // Clear the messages UI
    function clearMessagesUI() {
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.innerHTML = '<div class="chat-loading">Lade Nachrichten...</div>';
        }
    }
    
    // Open chat - COMPLETE REWORK: Simple and direct approach
    async function openChat(chatId, userId, username, keepCurrentView = false) {
        const numChatId = parseInt(chatId);
        const numUserId = parseInt(userId);
        
        if (!numChatId || !numUserId) {
            return;
        }
        
        // Always switch to the clicked chat, even if it's the same one
        // This ensures the chat is always refreshed and the user can switch between chats reliably
        
        // STEP 1: Abort all ongoing requests FIRST
        abortAllChatRequests();
        
        // STEP 2: ALWAYS clear old chat state when switching (even if same chat needs refresh)
        // This ensures clean state for the new chat
        clearMessagesUI();
        hideAnfrageStatusBar(); // Hide status bar when switching chats
        currentMediaFiles = [];
        currentMediaIndex = 0;
        selectedFiles = [];
        removeFilePreview();
        
        // Clear input field when switching chats
        const input = document.getElementById('chatInput');
        if (input) {
            input.value = '';
            input.style.height = 'auto';
        }
        
        // Close lightbox
        const lightbox = document.getElementById('chatMediaLightbox');
        if (lightbox && lightbox.style.display !== 'none') {
            lightbox.style.display = 'none';
            const lightboxVideo = document.getElementById('chatMediaLightboxVideo');
            const lightboxImage = document.getElementById('chatMediaLightboxImage');
            if (lightboxVideo) {
                lightboxVideo.pause();
                lightboxVideo.src = '';
            }
            if (lightboxImage) {
                lightboxImage.src = '';
            }
        }
        
        // STEP 3: Set new chat state IMMEDIATELY (before any async operations)
        // This is critical - set state first so all checks work correctly
        currentChatId = numChatId;
        currentContactId = numUserId;
        
        // STEP 4: Update UI elements
        const chatEmptyState = document.getElementById('chatEmptyState');
        const chatMessagesHeader = document.getElementById('chatMessagesHeader');
        const chatMessagesContainer = document.getElementById('chatMessagesContainer');
        const chatInputContainer = document.getElementById('chatInputContainer');
        
        // Always update UI elements when opening a chat (even if same chat, to ensure consistency)
        if (chatEmptyState) chatEmptyState.style.display = 'none';
        if (chatMessagesHeader) chatMessagesHeader.style.display = 'flex';
        if (chatMessagesContainer) chatMessagesContainer.style.display = 'flex';
        if (chatInputContainer) chatInputContainer.style.display = 'block';
        
        // Update contact info - check both contactsCache and archivedContactsCache
        let contact = contactsCache.find(c => c.user_id === numUserId);
        if (!contact) {
            contact = archivedContactsCache.find(c => c.user_id === numUserId);
        }
        
        // Check if this is an existing contact (from contacts or archived tab, not erkunden tab)
        // An existing contact must:
        // 1. Be in contactsCache or archivedContactsCache (appears in contacts or archived tab)
        // 2. Have a chat_id
        // Contacts from erkunden tab are NOT in these caches until they have messages
        const isExistingContact = contact && numChatId && !isNaN(numChatId) && numChatId > 0;
        
        // Update favorite status
        currentContactIsFavorite = contact ? (contact.is_favorite || false) : false;
        
        // Update favorite button - show for existing contacts (from contacts or archived tab, not erkunden tab)
        const favoriteBtn = document.getElementById('chatFavoriteBtn');
        const favoriteBtnMobile = document.getElementById('chatFavoriteBtnMobile');
        
        // Only show favorite button if:
        // 1. Contact exists in contactsCache or archivedContactsCache (is from contacts or archived tab, not erkunden tab)
        // 2. Contact has a chat_id
        const shouldShowFavoriteBtn = isExistingContact;
        
        if (favoriteBtn) {
            if (shouldShowFavoriteBtn) {
                favoriteBtn.style.display = 'flex';
                if (currentContactIsFavorite) {
                    favoriteBtn.classList.add('active');
                } else {
                    favoriteBtn.classList.remove('active');
                }
            } else {
                favoriteBtn.style.display = 'none';
            }
        }
        if (favoriteBtnMobile) {
            if (shouldShowFavoriteBtn) {
                favoriteBtnMobile.style.display = 'flex';
                if (currentContactIsFavorite) {
                    favoriteBtnMobile.classList.add('active');
                } else {
                    favoriteBtnMobile.classList.remove('active');
                }
            } else {
                favoriteBtnMobile.style.display = 'none';
            }
        }
        
        // Update contact info
        const userNameEl = document.getElementById('chatUserName');
        const avatarImg = document.getElementById('chatUserAvatar');
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        
        // Mobile header elements
        const userNameMobileEl = document.getElementById('chatUserNameMobile');
        const avatarMobileImg = document.getElementById('chatUserAvatarMobile');
        const mobileHeader = document.getElementById('chatMessagesMobileHeader');
        
        if (contact) {
            const displayName = contact.name || contact.username || 'Unbekannt';
            const avatarSrc = contact.avatar || basePath + 'assets/images/profile-placeholder.svg';
            
            if (userNameEl) userNameEl.textContent = displayName;
            if (avatarImg) {
                avatarImg.src = avatarSrc;
                avatarImg.alt = displayName;
            }
            
            // Update mobile header
            if (userNameMobileEl) userNameMobileEl.textContent = displayName;
            if (avatarMobileImg) {
                avatarMobileImg.src = avatarSrc;
                avatarMobileImg.alt = displayName;
            }
        } else if (username) {
            if (userNameEl) userNameEl.textContent = username;
            if (avatarImg) {
                avatarImg.src = basePath + 'assets/images/profile-placeholder.svg';
                avatarImg.alt = username;
            }
            
            // Update mobile header
            if (userNameMobileEl) userNameMobileEl.textContent = username;
            if (avatarMobileImg) {
                avatarMobileImg.src = basePath + 'assets/images/profile-placeholder.svg';
                avatarMobileImg.alt = username;
            }
        }
        
        // On mobile, show messages view and mobile header
        if (isMobile()) {
            showMobileMessagesView();
            if (mobileHeader) mobileHeader.style.display = 'flex';
        }
        
        // Update active contact
        document.querySelectorAll('.chat-contact-item, .chat-new-contact-item').forEach(item => {
            item.classList.remove('active');
        });
        
        let foundActive = false;
        document.querySelectorAll('.chat-contact-item').forEach(item => {
            const itemChatId = item.dataset.chatId ? parseInt(item.dataset.chatId) : null;
            if (itemChatId === numChatId && !foundActive) {
                item.classList.add('active');
                foundActive = true;
            }
        });
        
        if (!foundActive) {
            document.querySelectorAll('.chat-new-contact-item').forEach(item => {
                const itemUserId = item.dataset.userId ? parseInt(item.dataset.userId) : null;
                if (itemUserId === numUserId && !foundActive) {
                    item.classList.add('active');
                    foundActive = true;
                }
            });
        }
        
        // STEP 5: Load messages - Only load if contact is from contacts tab (has messages)
        // Use the contact variable already defined above
        if (isExistingContact) {
            // Only load messages for existing contacts (from contacts tab)
            try {
                // Load messages - this will always display, even if empty
                // Don't check currentChatId here - we just set it above, so it should be correct
                const messages = await loadMessages(numChatId, true);
                
                // Verify we're still on this chat after loading (race condition check)
                const numCurrentAfterLoad = currentChatId ? parseInt(currentChatId) : null;
                if (numCurrentAfterLoad !== numChatId) {
                    // Chat changed during load (user clicked another contact), don't update UI
                    return;
                }
                
                // We're still on the correct chat - finalize
                // Check and show status bar for confirmed anfragen
                await checkAndShowStatusBar(numChatId);
                // Scroll to bottom if messages exist
                if (messages && messages.length > 0) {
                    setTimeout(() => scrollToBottom(), 100);
                }
                
                // Mark messages as read (already done in loadMessages, but ensure it's done)
                await markMessagesAsRead(numChatId);
                await updateUnreadCount();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Fehler beim Laden der Nachrichten:', error);
                }
                // On error, verify we're still on the correct chat
                const numCurrentError = currentChatId ? parseInt(currentChatId) : null;
                if (numCurrentError === numChatId) {
                    // Still on same chat, show empty state
                    const messages = [];
                    messagesCache.set(numChatId, messages);
                    await displayMessages(messages, numChatId);
                }
                // If chat changed, do nothing (another openChat call will handle it)
            }
        } else {
            // New contact from erkunden tab - no messages to load, just show empty state
            const messages = [];
            messagesCache.set(numChatId, messages);
            await displayMessages(messages, numChatId);
        }
    }
    
    // Add contact to contacts tab by creating a chat and sending a welcome message
    async function addContactToContacts(userId, username, contactItem) {
        try {
            // Disable button to prevent multiple clicks
            const addBtn = contactItem.querySelector('.chat-new-contact-add-btn');
            if (addBtn) {
                addBtn.disabled = true;
                addBtn.style.opacity = '0.5';
            }
            
            // Step 1: Create chat
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const createResponse = await fetch(basePath + 'api/create-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${userId}`
            });
            
            const createData = await createResponse.json();
            
            if (!createData.success || !createData.chat_id) {
                throw new Error(createData.message || 'Fehler beim Erstellen des Chats');
            }
            
            const chatId = parseInt(createData.chat_id);
            
            // Store participant IDs
            if (createData.participant_ids && Array.isArray(createData.participant_ids) && createData.participant_ids.length >= 2) {
                chatParticipants.set(chatId, createData.participant_ids);
            }
            if (createData.current_user_id) {
                currentUserId = parseInt(createData.current_user_id);
            }
            
            // Step 2: Send a welcome message to make the chat appear in contacts
            const welcomeMessage = '👋'; // Simple welcome emoji
            const sendResponse = await fetch(basePath + 'api/send-chat-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `chat_id=${chatId}&receiver_id=${userId}&message=${encodeURIComponent(welcomeMessage)}`
            });
            
            const sendData = await sendResponse.json();
            
            if (!sendData.success) {
                throw new Error(sendData.message || 'Fehler beim Senden der Nachricht');
            }
            
            // Step 3: Remove contact from new contacts list
            contactItem.remove();
            
            // Step 4: Reload contacts to show the new contact in contacts tab
            await loadContacts(true);
            
            // Step 5: Switch to contacts tab
            switchView('contacts');
            
            // Step 6: Open the chat
            await openChat(chatId, userId, username);
            
        } catch (error) {
            console.error('Fehler beim Hinzufügen des Kontakts:', error);
            alert('Fehler beim Hinzufügen des Kontakts: ' + (error.message || 'Unbekannter Fehler'));
            
            // Re-enable button on error
            const addBtn = contactItem.querySelector('.chat-new-contact-add-btn');
            if (addBtn) {
                addBtn.disabled = false;
                addBtn.style.opacity = '1';
            }
        }
    }
    
    // Create new chat
    async function createNewChat(userId, username, reloadNewContacts = false) {
        // Cancel any ongoing create chat request
        if (currentCreateChatAbortController) {
            currentCreateChatAbortController.abort();
        }
        
        // Create new AbortController for this request
        const abortController = new AbortController();
        currentCreateChatAbortController = abortController;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + 'api/create-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${userId}`,
                signal: abortController.signal
            });
            
            // Check if request was aborted
            if (abortController.signal.aborted) {
                return;
            }
            
            const data = await response.json();
            
            // Always open the chat, even if user clicked another contact
            // The openChat function will handle the switch properly
            
            if (data.success && data.chat_id) {
                // Store participant IDs and current user ID for encryption
                if (data.participant_ids && Array.isArray(data.participant_ids) && data.participant_ids.length >= 2) {
                    chatParticipants.set(parseInt(data.chat_id), data.participant_ids);
                } else if (data.participant_ids) {
                    console.warn('Invalid participant_ids for new chat', data.chat_id, ':', data.participant_ids);
                }
                if (data.current_user_id) {
                    currentUserId = parseInt(data.current_user_id);
                }
                
                // Open chat but keep current view (don't switch to "kontakte" tab)
                // The contact should stay in "neue kontakte" until a message is sent
                await openChat(data.chat_id, userId, username, true);
                
                // Do NOT reload contacts here - the contact should not appear in "kontakte" 
                // until a message is actually sent
                // The get-chat-contacts.php API only returns chats with at least one message
                
                // Do NOT reload new contacts when just opening a chat
                // The contact should remain in "neue kontakte" until a message is sent
                // Only after sending a message will loadNewContacts() be called to remove it
                
                // Return chat_id for use by createChatFromContact
                return parseInt(data.chat_id);
            } else {
                // Only show error if request wasn't aborted
                if (!abortController.signal.aborted) {
                alert('Fehler beim Erstellen des Chats: ' + (data.message || 'Unbekannter Fehler'));
                }
            }
        } catch (error) {
            // Ignore AbortError - it's expected when switching chats quickly
            if (error.name !== 'AbortError') {
            console.error('Fehler:', error);
                if (!abortController.signal.aborted) {
            alert('Fehler beim Erstellen des Chats');
                }
            }
        } finally {
            // Clear abort controller if this was the current request
            if (currentCreateChatAbortController === abortController) {
                currentCreateChatAbortController = null;
            }
        }
        
        return null;
    }
    
    // Load messages - Simplified: Always display, even if empty
    async function loadMessages(chatId, markAsRead = true) {
        const numChatId = parseInt(chatId);
        if (!numChatId) {
            return [];
        }
        
        // Create AbortController
        const abortController = new AbortController();
        currentLoadMessagesAbortController = abortController;
        
        try {
            // Fetch messages
            // Note: We don't check currentChatId here because openChat sets it before calling this function
            // If the chat changed, the abort controller will handle it
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + `api/get-chat-messages.php?chat_id=${chatId}`, {
                signal: abortController.signal
            });
            
            // Check if request was aborted
            if (abortController.signal.aborted) {
                return [];
            }
            
            // Check again after fetch
            const numCurrentAfterFetch = currentChatId ? parseInt(currentChatId) : null;
            if (numCurrentAfterFetch !== numChatId) {
                return [];
            }
            
            const data = await response.json();
            
            // Check again after parsing
            const numCurrentAfterParse = currentChatId ? parseInt(currentChatId) : null;
            if (numCurrentAfterParse !== numChatId) {
                return [];
            }
            
            if (!data.success) {
                console.error('Failed to load messages:', data.message);
                // Still display empty state even on error
                const messages = [];
                messagesCache.set(numChatId, messages);
                await displayMessages(messages, numChatId);
                return messages;
            }
            
                const messages = data.messages || [];
            
            // Store participant IDs - CRITICAL for decryption
            if (data.participant_ids && Array.isArray(data.participant_ids) && data.participant_ids.length >= 2) {
                chatParticipants.set(numChatId, data.participant_ids);
                // Also ensure they're available immediately for decryption
                console.debug('Stored participant IDs for chat', numChatId, ':', data.participant_ids);
            } else {
                console.warn('No valid participant IDs received for chat', numChatId, ':', data.participant_ids);
            }
            if (data.current_user_id) {
                currentUserId = parseInt(data.current_user_id);
            }
            
            // Final check before displaying - but still display even if chat changed (for cleanup)
            const numCurrentFinal = currentChatId ? parseInt(currentChatId) : null;
            if (numCurrentFinal !== numChatId) {
                // Chat changed, but we still want to display empty state for cleanup
                clearMessagesUI();
                return [];
            }
            
            // Cache and display - ALWAYS display, even if messages array is empty
            messagesCache.set(numChatId, messages);
                if (messages.length > 0) {
                    const lastMsg = messages[messages.length - 1];
                    lastMessageIdCache.set(numChatId, lastMsg.id);
            } else {
                // Clear last message ID cache for empty chats
                lastMessageIdCache.delete(numChatId);
                }
                
            // ALWAYS call displayMessages, even if messages is empty
            // This ensures the empty state is shown correctly
            await displayMessages(messages, numChatId);
            
            // Mark as read
            if (markAsRead) {
                const numCurrentForRead = currentChatId ? parseInt(currentChatId) : null;
                if (numCurrentForRead === numChatId) {
                    await markMessagesAsRead(numChatId);
                    // Update unread count after marking as read
                    await updateUnreadCount();
                }
                }
                
                return messages;
            
        } catch (error) {
            if (error.name === 'AbortError') {
                return [];
            }
            const numCurrentError = currentChatId ? parseInt(currentChatId) : null;
            if (numCurrentError === numChatId) {
            console.error('Fehler beim Laden der Nachrichten:', error);
                // Still display empty state on error
                const messages = [];
                messagesCache.set(numChatId, messages);
                await displayMessages(messages, numChatId);
            }
            return [];
        } finally {
            if (currentLoadMessagesAbortController === abortController) {
                currentLoadMessagesAbortController = null;
            }
        }
    }
    
    // Display messages - COMPLETE REWORK: Simple and direct
    async function displayMessages(messages, expectedChatId = null) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) {
            return;
        }
        
        // Simple validation: check currentChatId
        const numCurrent = currentChatId ? parseInt(currentChatId) : null;
        const numExpected = expectedChatId ? parseInt(expectedChatId) : null;
        
        if (!numCurrent) {
            clearMessagesUI();
            return;
        }
        
        // If expected chat ID is provided, verify it matches
        if (numExpected && numExpected !== numCurrent) {
            clearMessagesUI();
            return;
        }
        
        // Verify messages belong to current chat (only if messages exist)
        if (messages && messages.length > 0) {
            for (const msg of messages) {
                const msgChatId = msg.chat_id ? parseInt(msg.chat_id) : null;
                if (msgChatId && msgChatId !== numCurrent) {
                    clearMessagesUI();
                    return;
                }
            }
        }
        
        // If no messages, show empty state - this is valid and should be displayed
        if (!messages || messages.length === 0) {
            // Get contact name from header or use fallback
            const userNameEl = document.getElementById('chatUserName');
            let contactName = 'dem Kontakt';
            
            if (userNameEl && userNameEl.textContent) {
                const name = userNameEl.textContent.trim();
                if (name && name !== '') {
                    contactName = name;
                }
            }
            
            // If we still don't have a name, try to get it from the contacts cache
            if (contactName === 'dem Kontakt' && currentContactId) {
                const contact = contactsCache.find(c => c.user_id === currentContactId);
                if (contact) {
                    contactName = contact.name || contact.username || 'dem Kontakt';
                }
            }
            
            messagesContainer.innerHTML = `
                <div class="chat-messages-empty">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <p class="chat-messages-empty-title">Noch keine Nachricht vorhanden</p>
                    <p class="chat-messages-empty-text">Schreib ${escapeHtml(contactName)} etwas!</p>
                </div>
            `;
            return;
        }
        
        // ==========================================
        // PHASE 3: PREPARE FOR RENDERING
        // ==========================================
        
        // Get current scroll position
        const wasAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 100;
        
        // Build HTML with date separators
        let html = '';
        let lastDate = null;
        
        // Get participant IDs for this chat
        // Use currentChatId if messages are empty, otherwise use first message's chat_id
        const chatId = (messages.length > 0 && messages[0].chat_id) ? parseInt(messages[0].chat_id) : numCurrent;
        let participantIds = chatId ? chatParticipants.get(chatId) : null;
        
        // If participant IDs are not in cache, try to get them from the first message
        // This can happen if displayMessages is called before loadMessages completes
        if (!participantIds && chatId && messages.length > 0) {
            // Try to get from any message that might have participant_ids
            // This is a fallback - normally they should be set by loadMessages
            console.warn('No participant IDs found in cache for chat:', chatId, '- will try to derive from messages');
        }
        
        // Process messages with async decryption
        const messagePromises = messages.map(async (msg, index) => {
            // Simple check: is chat still the same?
            const numCurrentDuring = currentChatId ? parseInt(currentChatId) : null;
            if (numExpected && numCurrentDuring !== numExpected) {
                return null;
            }
            
            const msgChatId = parseInt(msg.chat_id || chatId);
            const isSent = msg.is_sent;
            
            // Get participant IDs - try multiple sources
            let msgParticipantIds = msgChatId ? chatParticipants.get(msgChatId) : participantIds;
            
            // If still no participant IDs, try to get from cache again (might have been set by now)
            if (!msgParticipantIds && msgChatId) {
                msgParticipantIds = chatParticipants.get(msgChatId);
            }
            
            // No decryption - return message as-is
            const decryptedMessage = msg.message || '';
            
            return { msg, isSent, decryptedMessage, index };
        });
        
        const processedMessages = await Promise.all(messagePromises);
        const validProcessedMessages = processedMessages.filter(m => m !== null);
        
        // Final check after decryption
        const numCurrentAfterDecrypt = currentChatId ? parseInt(currentChatId) : null;
        if (numExpected && numCurrentAfterDecrypt !== numExpected) {
            clearMessagesUI();
            return;
        }
        
        // Verify we're still on the correct chat
        if (numCurrentAfterDecrypt !== numCurrent) {
            clearMessagesUI();
            return;
        }
        
        // First pass: Identify file groups - collect all files from messages within 5 minutes
        const fileGroups = [];
        let currentFileGroup = null;
        
        validProcessedMessages.forEach(({ msg, isSent, decryptedMessage, index }) => {
            const currentTime = parseTimestamp(msg.created_at);
            if (!currentTime) return;
            
            // Extract files from this message
            const files = extractFilesFromMessage(msg, decryptedMessage);
            const hasFiles = files.length > 0;
            
            if (hasFiles) {
                // Check if this message should be part of the current file group
                if (currentFileGroup && 
                    currentFileGroup.isSent === isSent &&
                    currentFileGroup.messages.length > 0) {
                    const lastMsgInGroup = currentFileGroup.messages[currentFileGroup.messages.length - 1];
                    const lastTime = parseTimestamp(lastMsgInGroup.msg.created_at);
                    if (lastTime) {
                        const diffMinutes = (currentTime - lastTime) / (1000 * 60);
                        if (diffMinutes <= 5) {
                            // Add to existing group
                            currentFileGroup.messages.push({ msg, decryptedMessage, files, index });
                            currentFileGroup.allFiles = currentFileGroup.allFiles.concat(files);
                            return;
                        }
                    }
                }
                
                // Start new file group
                currentFileGroup = {
                    isSent,
                    messages: [{ msg, decryptedMessage, files, index }],
                    allFiles: files,
                    startIndex: index
                };
                fileGroups.push(currentFileGroup);
            } else {
                // No files, reset current file group
                currentFileGroup = null;
            }
        });
        
        // Second pass: Render messages, combining files from groups
        validProcessedMessages.forEach(({ msg, isSent, decryptedMessage, index }) => {
            
            // Check if we need a date separator
            const currentDate = msg.created_at;
            const needsDateSeparator = !lastDate || !isSameDay(currentDate, lastDate);
            
            if (needsDateSeparator) {
                const dateLabel = formatDate(currentDate);
                html += `<div class="chat-date-separator">
                    <div class="chat-date-separator-line"></div>
                    <span class="chat-date-separator-text">${escapeHtml(dateLabel)}</span>
                    <div class="chat-date-separator-line"></div>
                </div>`;
                lastDate = currentDate;
            }
            
            // Check if this message is part of a file group
            const fileGroup = fileGroups.find(g => 
                g.messages.some(m => m.index === index)
            );
            
            const isInFileGroup = fileGroup !== undefined;
            const isFirstInFileGroup = fileGroup && fileGroup.messages[0].index === index;
            const isLastInFileGroup = fileGroup && fileGroup.messages[fileGroup.messages.length - 1].index === index;
            
            // Determine grouping based on next message (for 5-minute time period)
            // Also consider file groups - messages with files within 5 minutes should be grouped
            let isGrouped = false;
            let isFirstInGroup = true;
            let isLastInGroup = true;
            let showAvatar = true;
            let showTime = false;
            
            const currentTime = parseTimestamp(msg.created_at);
            
            // Check if message has files
            const files = extractFilesFromMessage(msg, decryptedMessage);
            const hasFiles = files.length > 0;
            
            // If message is in a file group, apply grouping rules FIRST
            // This ensures file uploads within 5 minutes get the same rounding rules
            if (isInFileGroup) {
                isGrouped = true;
                isFirstInGroup = isFirstInFileGroup;
                isLastInGroup = isLastInFileGroup;
            } else {
                // Only apply time-based grouping if not in a file group
            // Check previous message to see if we're part of a group
            if (index > 0) {
                const prevMsg = messages[index - 1];
                const prevTime = parseTimestamp(prevMsg.created_at);
                
                // Check if same sender and within 5 minutes
                if (currentTime && prevTime && prevMsg.is_sent === isSent) {
                    const diffMinutes = (currentTime - prevTime) / (1000 * 60);
                    if (diffMinutes <= 5) {
                        isGrouped = true;
                        isFirstInGroup = false; // Not the first in group if there's a previous message within 5 min
                    }
                }
            } else {
                // First message in the entire list is always first-in-group
                isFirstInGroup = true;
            }
            
            // Check next message to determine if this is last in group
            if (index < messages.length - 1) {
                const nextMsg = messages[index + 1];
                const nextTime = parseTimestamp(nextMsg.created_at);
                
                // Check if same sender and within 5 minutes
                if (currentTime && nextTime && nextMsg.is_sent === isSent) {
                    const diffMinutes = (nextTime - currentTime) / (1000 * 60);
                    if (diffMinutes <= 5) {
                        isGrouped = true; // This message is part of a group
                        isLastInGroup = false; // Not the last in the group
                        }
                    }
                    }
                }
            
                
                // Check if next message is more than 5 minutes later for time display
            if (index < messages.length - 1) {
                const nextMsg = messages[index + 1];
                const nextTime = parseTimestamp(nextMsg.created_at);
                
                if (currentTime && nextTime) {
                    const diffMinutes = (nextTime - currentTime) / (1000 * 60);
                    if (diffMinutes > 5 || nextMsg.is_sent !== isSent) {
                        showTime = true;
                    }
                }
            } else {
                // Last message always shows time
                showTime = true;
            }
            
            // Avatar is shown ONLY if it's the last in a group (bottom message of a 5-minute period)
            // For grouped messages, only the last one shows avatar
            // For non-grouped messages (standalone), show avatar
            if (isGrouped) {
                showAvatar = isLastInGroup; // Only show avatar if it's the last in the group
            }
            // If not grouped, showAvatar stays true (standalone message shows avatar)
            
            const time = showTime ? formatTime(msg.created_at) : '';
            const groupedClass = isGrouped ? ' grouped' : '';
            const firstInGroupClass = isGrouped && isFirstInGroup ? ' first-in-group' : '';
            const lastInGroupClass = isGrouped && isLastInGroup ? ' last-in-group' : '';
            const hasTimeClass = showTime ? ' has-time' : '';
            
            const avatarHtml = showAvatar 
                ? `<img class="chat-message-avatar" 
                         src="${escapeHtml(msg.avatar || (typeof getBasePath === 'function' ? getBasePath() : '') + 'assets/images/profile-placeholder.svg')}" 
                         alt="">`
                : '<div class="chat-message-avatar-spacer"></div>';
            
            // Generate message content - if this is the first message in a file group, render all files
            let messageContent;
            if (isInFileGroup && isFirstInFileGroup) {
                // Render all files from the group in this first message
                messageContent = generateMessageContentWithFileGroup(msg, decryptedMessage, fileGroup.allFiles, fileGroup);
            } else if (isInFileGroup) {
                // For other messages in the file group, only show text if any
                // Also check if any message in the group has text
                let hasTextInGroup = false;
                let textFromGroup = '';
                
                if (fileGroup && fileGroup.messages) {
                    for (const groupMsg of fileGroup.messages) {
                        const msgText = groupMsg.decryptedMessage;
                        if (msgText && msgText.trim() && !msgText.startsWith('[') && !msgText.startsWith('{') && !msgText.startsWith('data:')) {
                            hasTextInGroup = true;
                            textFromGroup = msgText;
                            break;
                        }
                    }
                }
                
                // Check current message for text
                const currentMessageText = generateMessageContent(msg, decryptedMessage, true); // Pass flag to skip files
                
                // Only show this message if it has text content
                if (hasTextInGroup && textFromGroup) {
                    messageContent = `<div class="chat-message-text">${escapeHtml(textFromGroup)}</div>`;
                } else if (currentMessageText && currentMessageText.trim()) {
                    messageContent = currentMessageText;
                } else {
                    // Skip this message entirely - it's part of file group but has no text
                    // Don't render it as a separate message bubble
                    return; // Skip to next iteration
                }
            } else {
                // Normal message rendering
                messageContent = generateMessageContent(msg, decryptedMessage);
                
                // Skip empty messages (no files, no text)
                if (!messageContent || !messageContent.trim()) {
                    return; // Skip to next iteration
                }
            }
            
            html += `
                <div class="chat-message ${isSent ? 'sent' : 'received'}${groupedClass}${firstInGroupClass}${lastInGroupClass}${hasTimeClass}" data-message-id="${msg.id}" data-is-sent="${isSent ? '1' : '0'}">
                    ${avatarHtml}
                    <div class="chat-message-content">
                        <div class="chat-message-bubble">${messageContent}</div>
                        ${showTime ? `<span class="chat-message-time">${time}</span>` : ''}
                    </div>
                </div>
            `;
        });
        
        // Final check before displaying
        const numCurrentBeforeDisplay = currentChatId ? parseInt(currentChatId) : null;
        if (numExpected && numCurrentBeforeDisplay !== numExpected) {
            clearMessagesUI();
            return;
        }
        
        // Display messages
        messagesContainer.innerHTML = html;
        
        // Attach context menu listeners to all messages
        attachContextMenuListeners();
        
        // Attach anfrage button listeners
        attachAnfrageButtonListeners();
        
        // Scroll to bottom if user was already at bottom
        if (wasAtBottom) {
            scrollToBottom();
        }
    }
    
    // Add single message to display (for new messages)
    async function addMessageToDisplay(message) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        // Verify this message belongs to the currently open chat
        const messageChatId = message.chat_id || (message.chat_id === null ? null : parseInt(message.chat_id));
        if (messageChatId !== null && messageChatId !== currentChatId) {
            // This message is for a different chat, don't display it
            return;
        }
        
        // Remove empty state if it exists (first message in chat)
        const emptyState = messagesContainer.querySelector('.chat-messages-empty');
        if (emptyState) {
            emptyState.remove();
        }
        
        // Also remove loading state if it exists
        const loadingState = messagesContainer.querySelector('.chat-loading');
        if (loadingState) {
            loadingState.remove();
        }
        
        const isSent = message.is_sent;
        const chatId = parseInt(message.chat_id || currentChatId);
        
        // No decryption - use message as-is
        const decryptedMessage = message.message || '';
        
        // Check if this message has files
        const files = extractFilesFromMessage(message, decryptedMessage);
        const hasFiles = files.length > 0;
        
        // If this message has files, check if we should re-render all messages to handle file grouping
        // Also re-render if within 5 minutes of previous message to ensure proper rounding rules
        let shouldRerender = false;
        if (hasFiles) {
            const lastMessageData = messagesCache.get(currentChatId);
            if (lastMessageData && lastMessageData.length > 0) {
                const lastMsg = lastMessageData[lastMessageData.length - 1];
                const lastTime = parseTimestamp(lastMsg.created_at);
                const currentTime = parseTimestamp(message.created_at);
                
                if (lastTime && currentTime && lastMsg.is_sent === isSent) {
                    const diffMinutes = (currentTime - lastTime) / (1000 * 60);
                    if (diffMinutes <= 5) {
                        // Always re-render if message with files is within 5 minutes
                        // This ensures proper grouping and rounding rules are applied
                        shouldRerender = true;
                    }
                }
            }
        }
        
        // Also check if last message has files and this message is within 5 minutes
        // This ensures rounding rules apply even if current message doesn't have files
        if (!shouldRerender) {
            const lastMessageData = messagesCache.get(currentChatId);
            if (lastMessageData && lastMessageData.length > 0) {
                const lastMsg = lastMessageData[lastMessageData.length - 1];
                const lastTime = parseTimestamp(lastMsg.created_at);
                const currentTime = parseTimestamp(message.created_at);
                
                if (lastTime && currentTime && lastMsg.is_sent === isSent) {
                    const diffMinutes = (currentTime - lastTime) / (1000 * 60);
                    if (diffMinutes <= 5) {
                        // Check if last message has files
                        // No decryption - use message as-is
                        const lastDecryptedMsg = lastMsg.message || '';
                        const lastMsgFiles = extractFilesFromMessage(lastMsg, lastDecryptedMsg);
                        if (lastMsgFiles.length > 0) {
                            // Last message has files, current message is within 5 minutes - re-render for proper grouping
                            shouldRerender = true;
                        }
                    }
                }
            }
        }
        
        // If we need to re-render for file grouping or rounding rules, reload all messages
        if (shouldRerender) {
            const messages = messagesCache.get(currentChatId) || [];
            await displayMessages(messages, currentChatId);
            return;
        }
        
        // Check if we need a date separator
        const lastMessageElement = messagesContainer.querySelector('.chat-message:last-child');
        let needsDateSeparator = false;
        
        if (lastMessageElement) {
            const lastMessageData = messagesCache.get(currentChatId);
            if (lastMessageData && lastMessageData.length > 0) {
                const lastMsg = lastMessageData[lastMessageData.length - 1];
                if (!isSameDay(message.created_at, lastMsg.created_at)) {
                    needsDateSeparator = true;
                }
            }
        } else {
            // First message, always show date separator
            needsDateSeparator = true;
        }
        
        // Add date separator if needed
        if (needsDateSeparator) {
            const dateLabel = formatDate(message.created_at);
            const dateSeparatorHtml = `
                <div class="chat-date-separator">
                    <div class="chat-date-separator-line"></div>
                    <span class="chat-date-separator-text">${escapeHtml(dateLabel)}</span>
                    <div class="chat-date-separator-line"></div>
                </div>
            `;
            messagesContainer.insertAdjacentHTML('beforeend', dateSeparatorHtml);
        }
        
        // Get the last message element to check if we can group with it
        let isGrouped = false;
        let isFirstInGroup = true;
        let showAvatar = true;
        
        if (lastMessageElement) {
            // Check if this new message is within 5 minutes and from same sender as last message
            const lastMessageData = messagesCache.get(currentChatId);
            if (lastMessageData && lastMessageData.length > 0) {
                const lastMsg = lastMessageData[lastMessageData.length - 1];
                const lastTime = parseTimestamp(lastMsg.created_at);
                const currentTime = parseTimestamp(message.created_at);
                
                if (lastTime && currentTime && lastMsg.is_sent === isSent) {
                    const diffMinutes = (currentTime - lastTime) / (1000 * 60);
                    if (diffMinutes <= 5) {
                        // Group with last message
                        isGrouped = true;
                        isFirstInGroup = false;
                        // New message is always last in group, so show avatar only on this one
                        // showAvatar stays true (new message shows avatar as it's the last)
                        
                        // Hide time on last message
                        const lastTimeElement = lastMessageElement.querySelector('.chat-message-time');
                        if (lastTimeElement) {
                            lastTimeElement.style.display = 'none';
                        }
                        
                        // Update last message classes - remove last-in-group, add grouped
                        // Keep first-in-group class if it exists (first message in group)
                        lastMessageElement.classList.remove('last-in-group');
                        lastMessageElement.classList.add('grouped');
                        
                        // Ensure first-in-group class is maintained for the first message
                        if (!lastMessageElement.classList.contains('first-in-group')) {
                            // Check if this is actually the first message in the group
                            const prevSibling = lastMessageElement.previousElementSibling;
                            if (!prevSibling || 
                                !prevSibling.classList.contains('chat-message') || 
                                prevSibling.classList.contains('chat-date-separator') ||
                                (prevSibling.classList.contains('sent') !== lastMessageElement.classList.contains('sent'))) {
                                lastMessageElement.classList.add('first-in-group');
                            }
                        }
                        
                        // IMPORTANT: Hide avatar on previous message (it's no longer last in group)
                        const lastAvatar = lastMessageElement.querySelector('.chat-message-avatar');
                        if (lastAvatar) {
                            const spacer = document.createElement('div');
                            spacer.className = 'chat-message-avatar-spacer';
                            lastAvatar.replaceWith(spacer);
                        }
                    }
                }
            }
        }
        
        // Avatar is shown ONLY if it's the last in a group
        // For new messages: if grouped, it's always last, so show avatar
        // If not grouped, show avatar (single message)
        
        // Always show time for the new message (it's the last one)
        const time = formatTime(message.created_at);
        const groupedClass = isGrouped ? ' grouped' : '';
        const firstInGroupClass = isGrouped && isFirstInGroup ? ' first-in-group' : '';
        const lastInGroupClass = ' last-in-group'; // New message is always last in group
        const hasTimeClass = ' has-time'; // Always show time for new messages
        
        const avatarHtml = showAvatar 
            ? `<img class="chat-message-avatar" 
                     src="${escapeHtml(message.avatar || (typeof getBasePath === 'function' ? getBasePath() : '') + 'assets/images/profile-placeholder.svg')}" 
                     alt="">`
            : '<div class="chat-message-avatar-spacer"></div>';
        
        // Generate message content (text, image, or video)
        const messageContent = generateMessageContent(message, decryptedMessage);
        
        const messageHtml = `
            <div class="chat-message ${isSent ? 'sent' : 'received'}${groupedClass}${firstInGroupClass}${lastInGroupClass}${hasTimeClass}" data-message-id="${message.id}" data-is-sent="${isSent ? '1' : '0'}">
                ${avatarHtml}
                <div class="chat-message-content">
                    <div class="chat-message-bubble">${messageContent}</div>
                    <span class="chat-message-time">${time}</span>
                </div>
            </div>
        `;
        
        messagesContainer.insertAdjacentHTML('beforeend', messageHtml);
        
        // Attach context menu listener to the new message
        const newMessageElement = messagesContainer.querySelector(`[data-message-id="${message.id}"]`);
        if (newMessageElement) {
            attachContextMenuToMessage(newMessageElement);
        }
        
        // Attach anfrage button listeners
        attachAnfrageButtonListeners();
        
        // Check if user is near bottom, then scroll
        const wasAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 100;
        if (wasAtBottom) {
            scrollToBottom();
        }
    }
    
    // Initialize chat input
    function initChatInput() {
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');
        const fileBtn = document.getElementById('chatFileBtn');
        const fileInput = document.getElementById('chatFileInput');
        const inputContainer = document.getElementById('chatInputContainer');
        const inputWrapper = inputContainer ? inputContainer.querySelector('.chat-input-wrapper') : null;
        
        if (!input || !sendBtn) return;
        
        // File upload button
        if (fileBtn && fileInput) {
            fileBtn.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', handleFileSelect);
        }
        
        // Drag and drop handlers for file uploads
        if (inputContainer) {
            // Prevent default drag behaviors on the container
            inputContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                inputContainer.classList.add('drag-over');
            });
            
            inputContainer.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Only remove drag-over if we're leaving the container itself
                if (!inputContainer.contains(e.relatedTarget)) {
                    inputContainer.classList.remove('drag-over');
                }
            });
            
            inputContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                inputContainer.classList.remove('drag-over');
                
                const files = Array.from(e.dataTransfer.files);
                if (files && files.length > 0) {
                    // Use the same file handling logic as file input
                    handleFileDrop(files);
                }
            });
        }
        
        // Also handle drag and drop on the input wrapper for better UX
        if (inputWrapper) {
            inputWrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            inputWrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const files = Array.from(e.dataTransfer.files);
                if (files && files.length > 0) {
                    handleFileDrop(files);
                }
            });
        }
        
        // Send on Enter
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Send button
        sendBtn.addEventListener('click', sendMessage);
    }
    
    // Handle file drop (similar to handleFileSelect but for drag and drop)
    function handleFileDrop(files) {
        if (!files || files.length === 0) return;
        
        const MAX_FILES = 10; // Maximum 10 files per message
        const maxSize = 100 * 1024 * 1024; // 100MB - increased for large videos
        
        // Check if adding these files would exceed the limit
        const remainingSlots = MAX_FILES - selectedFiles.length;
        if (remainingSlots <= 0) {
            alert(`Maximal ${MAX_FILES} Dateien pro Nachricht erlaubt.`);
            return;
        }
        
        // Validate all files
        const validFiles = [];
        const filesToProcess = files.slice(0, remainingSlots); // Only process files that fit
        
        if (files.length > remainingSlots) {
            alert(`Sie können nur noch ${remainingSlots} weitere Datei${remainingSlots > 1 ? 'en' : ''} hinzufügen. Maximal ${MAX_FILES} Dateien pro Nachricht.`);
        }
        
        for (const file of filesToProcess) {
            // Check file type
            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');
            
            if (!isImage && !isVideo) {
                alert(`Die Datei "${file.name}" ist kein Bild oder Video.`);
                continue;
            }
            
            // Check file size
            if (file.size > maxSize) {
                alert(`Die Datei "${file.name}" ist zu groß. Maximale Größe: 100MB`);
                continue;
            }
            
            validFiles.push(file);
        }
        
        if (validFiles.length === 0) {
            return;
        }
        
        // Add to selected files
        selectedFiles = [...selectedFiles, ...validFiles];
        
        // Show preview (this will update the grid view)
        showFilePreview(selectedFiles);
    }
    
    // Handle file selection
    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        if (!files || files.length === 0) return;
        
        const MAX_FILES = 10; // Maximum 10 files per message
        const maxSize = 100 * 1024 * 1024; // 100MB - increased for large videos
        
        // Check if adding these files would exceed the limit
        const remainingSlots = MAX_FILES - selectedFiles.length;
        if (remainingSlots <= 0) {
            alert(`Maximal ${MAX_FILES} Dateien pro Nachricht erlaubt.`);
            e.target.value = '';
            return;
        }
        
        // Validate all files
        const validFiles = [];
        const filesToProcess = files.slice(0, remainingSlots); // Only process files that fit
        
        if (files.length > remainingSlots) {
            alert(`Sie können nur noch ${remainingSlots} weitere Datei${remainingSlots > 1 ? 'en' : ''} hinzufügen. Maximal ${MAX_FILES} Dateien pro Nachricht.`);
        }
        
        for (const file of filesToProcess) {
        // Check file type
        const isImage = file.type.startsWith('image/');
        const isVideo = file.type.startsWith('video/');
        
        if (!isImage && !isVideo) {
                alert(`Die Datei "${file.name}" ist kein Bild oder Video.`);
                continue;
        }
        
            // Check file size
        if (file.size > maxSize) {
                alert(`Die Datei "${file.name}" ist zu groß. Maximale Größe: 100MB`);
                continue;
            }
            
            validFiles.push(file);
        }
        
        if (validFiles.length === 0) {
            e.target.value = '';
            return;
        }
        
        // Add to selected files
        selectedFiles = [...selectedFiles, ...validFiles];
        
        // Show preview
        showFilePreview(selectedFiles);
        
        // Reset input to allow selecting the same files again
        e.target.value = '';
    }
    
    // Show file preview
    function showFilePreview(files) {
        const preview = document.getElementById('chatFilePreview');
        if (!preview) return;
        
        if (!files || files.length === 0) {
            preview.style.display = 'none';
            preview.innerHTML = '';
            return;
        }
        
        preview.style.display = 'block';
        preview.innerHTML = '';
        
        // Create grid container for multiple files
        const gridContainer = document.createElement('div');
        gridContainer.className = 'chat-file-preview-grid';
        
        files.forEach((file, index) => {
            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');
        
        const previewWrapper = document.createElement('div');
        previewWrapper.className = 'chat-file-preview-wrapper';
            previewWrapper.dataset.fileIndex = index;
        
            // Media element
        if (isImage) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.className = 'chat-file-preview-image';
            img.alt = file.name;
            previewWrapper.appendChild(img);
            } else if (isVideo) {
            const video = document.createElement('video');
            video.src = URL.createObjectURL(file);
            video.className = 'chat-file-preview-video';
            video.controls = true;
            previewWrapper.appendChild(video);
        }
        
            // File info
        const fileInfo = document.createElement('div');
        fileInfo.className = 'chat-file-preview-info';
        fileInfo.innerHTML = `
            <span class="chat-file-preview-name">${escapeHtml(file.name)}</span>
            <span class="chat-file-preview-size">${formatFileSize(file.size)}</span>
        `;
        previewWrapper.appendChild(fileInfo);
        
            // Remove button
        const removeBtn = document.createElement('button');
        removeBtn.className = 'chat-file-preview-remove';
        removeBtn.innerHTML = '×';
        removeBtn.type = 'button';
        removeBtn.addEventListener('click', function() {
                removeFilePreview(index);
        });
        previewWrapper.appendChild(removeBtn);
        
            gridContainer.appendChild(previewWrapper);
        });
        
        preview.appendChild(gridContainer);
    }
    
    // Remove file preview
    function removeFilePreview(fileIndex) {
        const preview = document.getElementById('chatFilePreview');
        const fileInput = document.getElementById('chatFileInput');
        
        if (fileIndex !== undefined && fileIndex !== null && fileIndex >= 0) {
            // Remove specific file
            if (fileIndex < selectedFiles.length) {
                // Get the wrapper element to revoke URL
                const wrapper = preview?.querySelector(`[data-file-index="${fileIndex}"]`);
                if (wrapper) {
                    const mediaEl = wrapper.querySelector('img[src^="blob:"], video[src^="blob:"]');
                    if (mediaEl) {
                        URL.revokeObjectURL(mediaEl.src);
                    }
                }
                
                // Remove from array
                selectedFiles.splice(fileIndex, 1);
                
                // Update preview
                if (selectedFiles.length > 0) {
                    showFilePreview(selectedFiles);
                } else {
        if (preview) {
            preview.style.display = 'none';
            preview.innerHTML = '';
                    }
                }
            }
        } else {
            // Remove all files
            if (preview) {
            // Revoke object URLs to free memory
            const urls = preview.querySelectorAll('img[src^="blob:"], video[src^="blob:"]');
            urls.forEach(el => {
                URL.revokeObjectURL(el.src);
            });
                
                preview.style.display = 'none';
                preview.innerHTML = '';
            }
            
            selectedFiles = [];
        }
        
        if (fileInput) {
            fileInput.value = '';
        }
    }
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    // Send message - ensures files are fully uploaded before sending
    // Upload video in chunks to avoid post_max_size limits
    async function uploadVideoInChunks(file, chatId) {
        const CHUNK_SIZE = 1 * 1024 * 1024; // 1MB chunks - safe for default PHP configurations (2MB upload_max_filesize)
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        
        // Upload each chunk
        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);
            
            // Create a File object from the blob so PHP recognizes it as a file upload
            // Use the original file extension to help with MIME type detection
            const fileExtension = file.name.split('.').pop() || 'bin';
            const chunkFileName = `chunk_${chunkIndex}.${fileExtension}`;
            const chunkFile = new File([chunk], chunkFileName, { type: file.type });
            
            const formData = new FormData();
            formData.append('chat_id', chatId);
            formData.append('chunk', chunkFile, chunkFileName);
            formData.append('chunk_index', chunkIndex);
            formData.append('total_chunks', totalChunks);
            formData.append('file_name', file.name);
            formData.append('file_type', file.type);
            formData.append('file_size', file.size);
            
            const response = await fetch(basePath + 'api/upload-chunk.php', {
                method: 'POST',
                body: formData
            });
            
            let data;
            try {
                data = await response.json();
            } catch (e) {
                const text = await response.text();
                console.error('Invalid JSON response:', text);
                throw new Error('Ungültige Antwort vom Server: ' + text.substring(0, 100));
            }
            
            if (!data.success) {
                console.error('Chunk upload failed:', data);
                throw new Error(data.message || 'Fehler beim Hochladen des Chunks');
            }
            
            // If this is the last chunk, return the file path
            if (chunkIndex === totalChunks - 1 && data.file_path) {
                return {
                    file_path: data.file_path,
                    file_type: data.file_type || (file.type.startsWith('image/') ? 'image' : 'video'),
                    file_name: data.file_name || file.name
                };
            }
        }
        
        throw new Error('Upload abgeschlossen, aber keine Datei-Informationen erhalten');
    }
    
    async function sendMessage() {
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');
        const fileBtn = document.getElementById('chatFileBtn');
        const fileInput = document.getElementById('chatFileInput');
        
        if (!input || !currentChatId) return;
        
        const message = input.value.trim();
        const files = selectedFiles.length > 0 ? selectedFiles : [];
        
        // Need either message or files
        if (!message && files.length === 0) return;
        
        // Don't disable input - allow continuous typing
        // input.disabled = true;
        // sendBtn.disabled = true;
        // if (fileBtn) fileBtn.disabled = true;
        
        try {
            // Process files: all files (images and videos) are sent as files
            const imageFiles = [];
            const videoFiles = [];
            const largeVideoFiles = []; // Videos that need chunked upload
            const CHUNKED_UPLOAD_THRESHOLD = 2 * 1024 * 1024; // 2MB - use chunked upload for files larger than this (default PHP limit)
            
            for (const file of files) {
                const isImage = file.type.startsWith('image/');
                if (isImage) {
                    // Images are now sent as files, not base64
                    // Check if they need chunked upload (for very large images)
                    if (file.size > CHUNKED_UPLOAD_THRESHOLD) {
                        // Large images can use chunked upload too
                        largeVideoFiles.push(file);
                    } else {
                        imageFiles.push(file);
                    }
                } else {
                    // Video files - check if they need chunked upload
                    if (file.size > CHUNKED_UPLOAD_THRESHOLD) {
                        largeVideoFiles.push(file);
                    } else {
                        videoFiles.push(file);
                    }
                }
            }
            
            // Upload large files (videos and large images) in chunks first
            const uploadedVideoFiles = [];
            for (const largeFile of largeVideoFiles) {
                const uploadResult = await uploadVideoInChunks(largeFile, currentChatId);
                uploadedVideoFiles.push(uploadResult);
            }
            
            // Prepare message content - just text now, no base64
            let messageContent = message || '';
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            
            // Use fetch with FormData - ensures complete upload before response
            const formData = new FormData();
            formData.append('chat_id', currentChatId);
            
            // Add image and video files if present (regular upload)
            const allSmallFiles = [...imageFiles, ...videoFiles];
            if (allSmallFiles.length > 0) {
                allSmallFiles.forEach((file) => {
                    formData.append('files[]', file);
                });
            }
            
            // Add already uploaded large files as metadata
            if (uploadedVideoFiles.length > 0) {
                formData.append('uploaded_videos', JSON.stringify(uploadedVideoFiles));
            }
            
            // Add message
            if (messageContent) {
                formData.append('message', messageContent);
            } else {
                formData.append('message', '');
            }
            
            // Send request - fetch ensures complete upload before returning response
            const response = await fetch(basePath + 'api/send-chat-message.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                input.value = '';
                input.style.height = 'auto';
                removeFilePreview(); // This will clear selectedFiles
                selectedFiles = []; // Ensure it's cleared
                
                // Add the sent message to cache instead of reloading all messages
                if (data.sent_message) {
                    const sentMsg = data.sent_message;
                    let existingMessages = messagesCache.get(currentChatId) || [];
                    
                    // Check if message already exists (might be merged on server)
                    // Also check if this message should be merged with a recent message from the same sender
                    let existingIndex = existingMessages.findIndex(msg => msg.id === sentMsg.id);
                    
                    // If not found by ID, check if it should be merged with the last message
                    // (server might have merged it into a different message)
                    if (existingIndex < 0 && sentMsg.file_type === 'multiple' && sentMsg.file_path) {
                        const sentTime = parseTimestamp(sentMsg.created_at);
                        const senderId = sentMsg.sender_id || (sentMsg.is_sent ? currentUserId : null);
                        
                        if (sentTime && senderId !== null && existingMessages.length > 0) {
                            // Check the last few messages from the same sender
                            for (let i = existingMessages.length - 1; i >= Math.max(0, existingMessages.length - 5); i--) {
                                const msg = existingMessages[i];
                                const msgTime = parseTimestamp(msg.created_at);
                                const msgSenderId = msg.sender_id || (msg.is_sent ? currentUserId : null);
                                
                                if (msgTime && msgSenderId === senderId) {
                                    const timeDiff = Math.abs(sentTime - msgTime) / 1000; // seconds
                                    
                                    // If within 30 seconds and has files, check if files match
                                    if (timeDiff <= 30 && (msg.file_path || msg.file_type === 'multiple')) {
                                        try {
                                            // Parse sent message files
                                            const sentFiles = JSON.parse(sentMsg.file_path);
                                            const sentFilePaths = Array.isArray(sentFiles) 
                                                ? sentFiles.map(f => (f.path || '').toLowerCase()).filter(p => p)
                                                : [];
                                            
                                            // Parse existing message files
                                            let msgFilePaths = [];
                                            if (msg.file_type === 'multiple' && msg.file_path) {
                                                const msgFiles = JSON.parse(msg.file_path);
                                                if (Array.isArray(msgFiles)) {
                                                    msgFilePaths = msgFiles.map(f => (f.path || '').toLowerCase()).filter(p => p);
                                                }
                                            } else if (msg.file_path) {
                                                msgFilePaths = [(msg.file_path || '').toLowerCase()].filter(p => p);
                                            }
                                            
                                            // Check if all sent files are in the existing message
                                            const allFilesMatch = sentFilePaths.length > 0 && 
                                                sentFilePaths.every(sentPath => 
                                                    msgFilePaths.some(msgPath => 
                                                        sentPath === msgPath || 
                                                        sentPath.includes(msgPath) || 
                                                        msgPath.includes(sentPath)
                                                    )
                                                );
                                            
                                            if (allFilesMatch && sentFilePaths.length >= msgFilePaths.length) {
                                                // This is the merged version - update the existing message
                                                existingIndex = i;
                                                break;
                                            }
                                        } catch (e) {
                                            // Parsing failed, continue checking
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    if (existingIndex >= 0) {
                        // Message was merged - update existing message in cache
                        // Make sure we preserve all fields from the updated message
                        const existingMsg = existingMessages[existingIndex];
                        
                        // Check if files were actually merged (file_type changed to 'multiple' or file_path changed)
                        const wasMerged = (sentMsg.file_type === 'multiple' && existingMsg.file_type !== 'multiple') ||
                                         (sentMsg.file_path !== existingMsg.file_path);
                        
                        // Update the message with all new data
                        existingMessages[existingIndex] = {
                            ...existingMsg,
                            ...sentMsg,
                            // Ensure file_path and file_type are updated
                            file_path: sentMsg.file_path,
                            file_type: sentMsg.file_type,
                            message: sentMsg.message,
                            encrypted: false,
                            // Preserve other important fields
                            chat_id: sentMsg.chat_id || existingMsg.chat_id,
                            is_sent: sentMsg.is_sent !== undefined ? sentMsg.is_sent : existingMsg.is_sent
                        };
                        
                        // IMPORTANT: If message was merged, remove any duplicate messages that were sent
                        // within a short time by the same sender (these should have been merged on server)
                        if (wasMerged && sentMsg.file_type === 'multiple' && sentMsg.file_path) {
                            const mergedTime = parseTimestamp(sentMsg.created_at);
                            const senderId = sentMsg.sender_id || (sentMsg.is_sent ? currentUserId : null);
                            
                            if (mergedTime && senderId !== null) {
                                // Parse merged files to get all file paths
                                let mergedFilePaths = [];
                                try {
                                    const mergedFiles = JSON.parse(sentMsg.file_path);
                                    if (Array.isArray(mergedFiles)) {
                                        mergedFilePaths = mergedFiles.map(f => (f.path || '').toLowerCase()).filter(p => p);
                                    }
                                } catch (e) {
                                    // If parsing fails, skip duplicate removal
                                }
                                
                                if (mergedFilePaths.length > 0) {
                                    // Remove messages that:
                                    // 1. Are from the same sender
                                    // 2. Were created within 30 seconds of the merged message (files sent quickly get merged)
                                    // 3. Have files that match files in the merged message
                                    // 4. Are NOT the merged message itself
                                    existingMessages = existingMessages.filter((msg, idx) => {
                                        if (idx === existingIndex) return true; // Keep the merged message
                                        
                                        const msgTime = parseTimestamp(msg.created_at);
                                        if (!msgTime) return true;
                                        
                                        const msgSenderId = msg.sender_id || (msg.is_sent ? currentUserId : null);
                                        const isSameSender = msgSenderId === senderId;
                                        
                                        if (!isSameSender) return true; // Keep messages from different senders
                                        
                                        // Check if message was sent within 30 seconds (quick file uploads get merged)
                                        const timeDiffSeconds = Math.abs(mergedTime - msgTime) / 1000;
                                        
                                        if (timeDiffSeconds <= 30 && (msg.file_path || msg.file_type)) {
                                            // Check if this message's files are in the merged message
                                            if (msg.file_path) {
                                                try {
                                                    // Parse this message's files
                                                    let msgFilePaths = [];
                                                    if (msg.file_type === 'multiple' || (typeof msg.file_path === 'string' && (msg.file_path.startsWith('[') || msg.file_path.startsWith('{')))) {
                                                        const msgFiles = JSON.parse(msg.file_path);
                                                        if (Array.isArray(msgFiles)) {
                                                            msgFilePaths = msgFiles.map(f => (f.path || '').toLowerCase()).filter(p => p);
                                                        } else if (msgFiles && msgFiles.path) {
                                                            msgFilePaths = [(msgFiles.path || '').toLowerCase()].filter(p => p);
                                                        }
                                                    } else {
                                                        // Single file
                                                        msgFilePaths = [(msg.file_path || '').toLowerCase()].filter(p => p);
                                                    }
                                                    
                                                    // Check if any of this message's files are in the merged files
                                                    const hasMatchingFile = msgFilePaths.some(msgPath => 
                                                        mergedFilePaths.some(mergedPath => 
                                                            msgPath === mergedPath || 
                                                            msgPath.includes(mergedPath) || 
                                                            mergedPath.includes(msgPath)
                                                        )
                                                    );
                                                    
                                                    // If this message's files are in the merged message, remove it
                                                    if (hasMatchingFile) {
                                                        return false; // Remove this duplicate
                                                    }
                                                } catch (e) {
                                                    // If parsing fails, be conservative and keep the message
                                                }
                                            }
                                        }
                                        
                                        return true; // Keep the message
                                    });
                                }
                            }
                        }
                        
                        messagesCache.set(currentChatId, existingMessages);
                        
                        // Force a full re-render to show updated grid view with merged files
                        // Clear the container first to ensure clean render
                        const messagesContainer = document.getElementById('chatMessages');
                        if (messagesContainer) {
                            messagesContainer.innerHTML = '';
                        }
                        
                        // Re-render all messages to show updated grid view with merged files
                        await displayMessages(existingMessages, currentChatId);
                        
                        // Scroll to bottom to show the updated message
                        scrollToBottom();
                        
                        // Update last message ID cache
                        lastMessageIdCache.set(currentChatId, sentMsg.id);
                    } else {
                        // New message - add to cache
                        const updatedMessages = [...existingMessages, sentMsg];
                        messagesCache.set(currentChatId, updatedMessages);
                        
                        // Add message to display without reloading all messages
                        await addMessageToDisplay(sentMsg);
                        
                        // Update last message ID cache
                        lastMessageIdCache.set(currentChatId, sentMsg.id);
                    }
                }
                
                // Immediately check for any updates (in case server merged messages)
                if (currentChatId) {
                    // Small delay to ensure server has processed the merge
                    setTimeout(async () => {
                        if (currentChatId) {
                            await checkForNewMessages(currentChatId);
                        }
                    }, 500);
                }
                
                // Reload contacts to update last message and show this contact in "kontakte"
                // Now that a message has been sent, the contact will appear in "kontakte" 
                // because get-chat-contacts.php only returns chats with at least one message
                // Reset to true to reload the entire list and show the new contact
                await loadContacts(true);
                
                // Update unread count after loading contacts
                await updateUnreadCount();
                
                // Reload new contacts to remove this contact from "neue kontakte" 
                // since a message has now been exchanged
                // The get-uncontacted-users.php API excludes users who have exchanged messages
                await loadNewContacts(true);
                
                // Switch to contacts view if currently viewing new contacts
                // This provides visual feedback that the contact has moved to "kontakte"
                const newContactsTab = document.getElementById('chatNewContactsTab');
                if (newContactsTab && newContactsTab.classList.contains('active')) {
                    switchView('contacts');
                }
                
                // Update unread count after sending message
                await updateUnreadCount();
            } else {
                alert('Fehler beim Senden: ' + (data.message || 'Unbekannter Fehler'));
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Senden der Nachricht: ' + (error.message || 'Unbekannter Fehler'));
        } finally {
            // Input is no longer disabled, so no need to re-enable
            // input.disabled = false;
            // sendBtn.disabled = false;
            // if (fileBtn) fileBtn.disabled = false;
            input.focus();
        }
    }
    
    // Polling for new messages
    function startPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
        
        pollingInterval = setInterval(async () => {
            if (!isMinimized && !chatBox.classList.contains('hidden')) {
                // Always update contacts to check for new messages and status changes (lightweight)
                // Use reset=false to avoid reloading all contacts, just update the list
                // The displayContacts function will handle duplicate filtering
                await loadContacts(false);
                
                // Always check for new messages in the currently open chat (live updates)
                // This ensures real-time updates even when messages are already read
                if (currentChatId) {
                    await checkForNewMessages(currentChatId);
                }
            } else {
                // Even when minimized or hidden, update contacts list for status changes
                // This ensures contact status (online/offline) stays updated
                await loadContacts(false);
            }
            
            // Always update unread count (even when chat is closed or minimized)
            // This ensures badge stays updated
            await updateUnreadCount();
        }, 1500); // Poll every 1.5 seconds for more responsive live updates
    }
    
    // Check if there are new messages without loading all messages
    async function checkForNewMessages(chatId) {
        let abortController = null;
        
        try {
            // Verify we're still on this chat (user might have switched)
            if (currentChatId !== chatId) {
                return;
            }
            
            // Cancel any ongoing check messages request
            if (currentCheckMessagesAbortController) {
                currentCheckMessagesAbortController.abort();
            }
            
            // Create new AbortController for this request
            abortController = new AbortController();
            currentCheckMessagesAbortController = abortController;
            
            // Get contacts to check if there are new messages
            const contact = contactsCache.find(c => c.chat_id === chatId);
            if (!contact) return;
            
            // Always check for new messages (live updates), not just when unread_count > 0
            // This ensures real-time updates even when messages are already read
            
                // Double-check we're still on this chat
                if (currentChatId !== chatId) {
                    return;
                }
                
                // Get the last message ID from cache
                const cachedLastId = lastMessageIdCache.get(chatId);
                
                // Check for new messages only (lightweight)
                const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                const response = await fetch(basePath + `api/get-chat-messages.php?chat_id=${chatId}&last_id=${cachedLastId || 0}`, {
                    signal: abortController.signal
                });
                
                // Check if request was aborted
                if (abortController.signal.aborted) {
                    return;
                }
                
                const data = await response.json();
                
                // Verify we're still on this chat after fetch
                if (currentChatId !== chatId) {
                    return;
                }
                
                // Store participant IDs and current user ID for encryption
                if (data.participant_ids && Array.isArray(data.participant_ids) && data.participant_ids.length >= 2) {
                    chatParticipants.set(chatId, data.participant_ids);
                } else if (data.participant_ids) {
                    console.warn('Invalid participant_ids from polling for chat', chatId);
                }
                if (data.current_user_id) {
                    currentUserId = parseInt(data.current_user_id);
                }
                
                if (data.success && data.messages) {
                    const newMessages = data.messages;
                    if (newMessages.length > 0) {
                        // New messages found - add them to existing messages
                        const existingMessages = messagesCache.get(chatId) || [];
                        const existingIds = new Set(existingMessages.map(m => m.id));
                        
                        // Filter out duplicates
                        const uniqueNewMessages = newMessages.filter(msg => !existingIds.has(msg.id));
                        
                        if (uniqueNewMessages.length > 0) {
                            // Verify one more time before displaying
                            if (currentChatId !== chatId) {
                                return;
                            }
                            
                            // Process messages - check for merges first
                            let updatedMessages = [...existingMessages];
                            let needsFullRerender = false;
                            
                            for (const newMsg of uniqueNewMessages) {
                                // Check again before each message
                                if (currentChatId !== chatId) {
                                    break;
                                }
                                
                                // Check if this is an update to an existing message (merged)
                                const existingIndex = updatedMessages.findIndex(msg => msg.id === newMsg.id);
                                
                                if (existingIndex >= 0) {
                                    // Message was updated (likely merged) - update it
                                    const existingMsg = updatedMessages[existingIndex];
                                    const wasMerged = (newMsg.file_type === 'multiple' && existingMsg.file_type !== 'multiple') ||
                                                     (newMsg.file_path !== existingMsg.file_path);
                                    
                                    if (wasMerged) {
                                        updatedMessages[existingIndex] = {
                                            ...existingMsg,
                                            ...newMsg,
                                            file_path: newMsg.file_path,
                                            file_type: newMsg.file_type,
                                            message: newMsg.message
                                        };
                                        needsFullRerender = true;
                                        
                                        // Remove duplicates (same logic as in sendMessage)
                                        if (newMsg.file_type === 'multiple' && newMsg.file_path) {
                                            const mergedTime = parseTimestamp(newMsg.created_at);
                                            const senderId = newMsg.sender_id || (newMsg.is_sent ? currentUserId : null);
                                            
                                            if (mergedTime && senderId !== null) {
                                                try {
                                                    const mergedFiles = JSON.parse(newMsg.file_path);
                                                    const mergedFilePaths = Array.isArray(mergedFiles) 
                                                        ? mergedFiles.map(f => (f.path || '').toLowerCase()).filter(p => p)
                                                        : [];
                                                    
                                                    if (mergedFilePaths.length > 0) {
                                                        updatedMessages = updatedMessages.filter((msg, idx) => {
                                                            if (idx === existingIndex) return true;
                                                            
                                                            const msgTime = parseTimestamp(msg.created_at);
                                                            if (!msgTime) return true;
                                                            
                                                            const msgSenderId = msg.sender_id || (msg.is_sent ? currentUserId : null);
                                                            if (msgSenderId !== senderId) return true;
                                                            
                                                            const timeDiffSeconds = Math.abs(mergedTime - msgTime) / 1000;
                                                            if (timeDiffSeconds > 30 || !msg.file_path) return true;
                                                            
                                                            try {
                                                                let msgFilePaths = [];
                                                                if (msg.file_type === 'multiple' && typeof msg.file_path === 'string' && (msg.file_path.startsWith('[') || msg.file_path.startsWith('{'))) {
                                                                    const msgFiles = JSON.parse(msg.file_path);
                                                                    if (Array.isArray(msgFiles)) {
                                                                        msgFilePaths = msgFiles.map(f => (f.path || '').toLowerCase()).filter(p => p);
                                                                    }
                                                                } else {
                                                                    msgFilePaths = [(msg.file_path || '').toLowerCase()].filter(p => p);
                                                                }
                                                                
                                                                const hasMatchingFile = msgFilePaths.some(msgPath => 
                                                                    mergedFilePaths.some(mergedPath => 
                                                                        msgPath === mergedPath || 
                                                                        msgPath.includes(mergedPath) || 
                                                                        mergedPath.includes(msgPath)
                                                                    )
                                                                );
                                                                
                                                                return !hasMatchingFile;
                                                            } catch (e) {
                                                                return true;
                                                            }
                                                        });
                                                    }
                                                } catch (e) {
                                                    // Parsing failed
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // New message - add it
                                    updatedMessages.push(newMsg);
                                }
                            }
                            
                            // Update cache
                            messagesCache.set(chatId, updatedMessages);
                            
                            if (needsFullRerender) {
                                // Full re-render for merged messages
                                const messagesContainer = document.getElementById('chatMessages');
                                if (messagesContainer) {
                                    messagesContainer.innerHTML = '';
                                }
                                await displayMessages(updatedMessages, chatId);
                                scrollToBottom();
                            } else {
                                // Add only new messages to display
                                for (const newMsg of uniqueNewMessages) {
                                    if (currentChatId !== chatId) break;
                                    
                                    const existingIndex = updatedMessages.findIndex(msg => msg.id === newMsg.id);
                                    if (existingIndex < 0 || existingIndex === updatedMessages.length - 1) {
                                        // Only add if it's truly new (not an update)
                                await addMessageToDisplay(newMsg);
                                    }
                                }
                            }
                                
                                // Update last message ID cache
                            if (updatedMessages.length > 0) {
                                const lastMsg = updatedMessages[updatedMessages.length - 1];
                                lastMessageIdCache.set(chatId, lastMsg.id);
                            }
                            
                            // Mark as read only if still on this chat
                            if (currentChatId === chatId) {
                                await markMessagesAsRead(chatId);
                                // Update unread count after marking as read
                                await updateUnreadCount();
                        }
                    }
                }
            }
        } catch (error) {
            // Ignore AbortError - it's expected when switching chats
            if (error.name !== 'AbortError') {
            console.error('Fehler beim Prüfen neuer Nachrichten:', error);
            }
        } finally {
            // Clear abort controller if this was the current request
            if (abortController && currentCheckMessagesAbortController === abortController) {
                currentCheckMessagesAbortController = null;
            }
        }
    }
    
    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }
    
    // Heartbeat for user activity
    function startHeartbeat() {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
        }
        
        // Send initial heartbeat
        sendHeartbeat();
        
        // Send heartbeat every 60 seconds to keep user online
        heartbeatInterval = setInterval(() => {
            sendHeartbeat();
        }, 60000);
    }
    
    async function sendHeartbeat() {
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            await fetch(basePath + 'api/update-user-activity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
        } catch (error) {
            // Silently fail - user might be offline
            console.debug('Heartbeat failed:', error);
        }
    }
    
    function stopHeartbeat() {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
    }
    
    // Make stopHeartbeat available globally for logout
    window.stopHeartbeat = stopHeartbeat;
    
    // Notification polling for unread count
    function startNotificationPolling() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
        }
        
        // Update immediately
        updateUnreadCount();
        
        // Then update every 2 seconds
        notificationInterval = setInterval(() => {
            updateUnreadCount();
        }, 2000);
    }
    
    function stopNotificationPolling() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
            notificationInterval = null;
        }
    }
    
    // Update unread count and badge
    async function updateUnreadCount() {
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + 'api/get-unread-count.php');
            const data = await response.json();
            
            if (data.success) {
                const newUnreadCount = intval(data.total_unread) || 0;
                
                // Only update if count changed
                if (newUnreadCount !== totalUnreadCount) {
                    totalUnreadCount = newUnreadCount;
                    updateChatToggleBadge(totalUnreadCount);
                    
                    // Update contacts list if chat is open
                    if (!chatBox.classList.contains('hidden')) {
                        await loadContacts(false);
                    }
                }
            }
        } catch (error) {
            // Silently fail - user might be offline
            console.debug('Failed to update unread count:', error);
        }
    }
    
    // Update chat toggle button badge
    function updateChatToggleBadge(count) {
        const toggleBtn = document.getElementById('chatToggleBtn');
        const toggleBtnMobile = document.getElementById('chatToggleBtnMobile');
        
        function updateBadge(btn) {
            if (!btn) return;
        
        // Remove existing badge
            let badge = btn.querySelector('.chat-notification-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'chat-notification-badge';
                    btn.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count.toString();
            badge.style.display = 'flex';
        } else {
            if (badge) {
                badge.style.display = 'none';
            }
        }
        }
        
        updateBadge(toggleBtn);
        updateBadge(toggleBtnMobile);
    }
    
    // Helper function to parse int safely
    function intval(value) {
        const parsed = parseInt(value, 10);
        return isNaN(parsed) ? 0 : parsed;
    }
    
    // Context menu for messages
    let contextMenu = null;
    let currentContextMessageId = null;
    let currentContextMessageElement = null;
    
    // Create context menu element
    function createContextMenu() {
        if (contextMenu) return contextMenu;
        
        contextMenu = document.createElement('div');
        contextMenu.id = 'chatMessageContextMenu';
        contextMenu.className = 'chat-message-context-menu';
        contextMenu.innerHTML = `
            <button class="context-menu-item" data-action="copy">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                <span>Kopieren</span>
            </button>
            <button class="context-menu-item" data-action="delete" id="contextMenuDelete">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span>Löschen</span>
            </button>
        `;
        document.body.appendChild(contextMenu);
        
        // Add event listeners to menu items
        contextMenu.querySelectorAll('.context-menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                handleContextMenuAction(this.dataset.action);
                hideContextMenu();
            });
        });
        
        return contextMenu;
    }
    
    // Show context menu
    function showContextMenu(x, y, messageId, messageElement) {
        createContextMenu();
        
        currentContextMessageId = messageId;
        currentContextMessageElement = messageElement;
        
        const isSent = messageElement.dataset.isSent === '1';
        const deleteBtn = contextMenu.querySelector('#contextMenuDelete');
        
        // Only show delete option for sent messages
        if (deleteBtn) {
            deleteBtn.style.display = isSent ? 'flex' : 'none';
        }
        
        contextMenu.style.display = 'block';
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        
        // Adjust position if menu goes off screen
        setTimeout(() => {
            const rect = contextMenu.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                contextMenu.style.left = (x - rect.width) + 'px';
            }
            if (rect.bottom > window.innerHeight) {
                contextMenu.style.top = (y - rect.height) + 'px';
            }
        }, 0);
    }
    
    // Hide context menu
    function hideContextMenu() {
        if (contextMenu) {
            contextMenu.style.display = 'none';
        }
        currentContextMessageId = null;
        currentContextMessageElement = null;
    }
    
    // Handle context menu actions
    async function handleContextMenuAction(action) {
        if (!currentContextMessageId || !currentContextMessageElement) return;
        
        const messageId = parseInt(currentContextMessageId);
        const messages = messagesCache.get(currentChatId) || [];
        const message = messages.find(m => m.id === messageId);
        
        if (!message) return;
        
        switch (action) {
            case 'copy':
                await copyMessage(message);
                break;
            case 'delete':
                await deleteMessage(messageId);
                break;
        }
    }
    
    // Copy message to clipboard
    async function copyMessage(message) {
        try {
            // No decryption - use message as-is
            const decryptedMessage = message.message || '';
            
            // Extract text from message (remove file data)
            let textToCopy = '';
            if (decryptedMessage) {
                // Check if it's a file (JSON or base64)
                if (decryptedMessage.startsWith('[') || decryptedMessage.startsWith('{') || decryptedMessage.startsWith('data:')) {
                    try {
                        const parsed = JSON.parse(decryptedMessage);
                        if (Array.isArray(parsed)) {
                            // It's a file array, extract file names
                            textToCopy = parsed.map(f => f.name || 'Datei').join(', ');
                        } else {
                            textToCopy = decryptedMessage;
                        }
                    } catch (e) {
                        textToCopy = decryptedMessage;
                    }
                } else {
                    textToCopy = decryptedMessage;
                }
            }
            
            if (textToCopy) {
                await navigator.clipboard.writeText(textToCopy);
                // Show feedback
                showContextMenuFeedback('Kopiert!');
            }
        } catch (error) {
            console.error('Error copying message:', error);
            showContextMenuFeedback('Fehler beim Kopieren');
        }
    }
    
    // Delete message
    async function deleteMessage(messageId) {
        // No confirmation needed - delete immediately
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + 'api/delete-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `message_id=${messageId}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove message from cache
                const messages = messagesCache.get(currentChatId) || [];
                const updatedMessages = messages.filter(m => m.id !== messageId);
                messagesCache.set(currentChatId, updatedMessages);
                
                // Remove message from display
                if (currentContextMessageElement) {
                    currentContextMessageElement.remove();
                }
                
                // Reload messages to ensure consistency
                if (currentChatId) {
                    await loadMessages(currentChatId, false);
                }
            } else {
                alert('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'));
            }
        } catch (error) {
            console.error('Error deleting message:', error);
            alert('Fehler beim Löschen der Nachricht');
        }
    }
    
    // Show feedback message
    function showContextMenuFeedback(text) {
        const feedback = document.createElement('div');
        feedback.className = 'context-menu-feedback';
        feedback.textContent = text;
        document.body.appendChild(feedback);
        
        setTimeout(() => {
            feedback.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            feedback.classList.remove('show');
            setTimeout(() => {
                feedback.remove();
            }, 300);
        }, 2000);
    }
    
    // Attach context menu listeners to all messages
    function attachContextMenuListeners() {
        const messages = document.querySelectorAll('.chat-message[data-message-id]');
        messages.forEach(messageElement => {
            attachContextMenuToMessage(messageElement);
        });
    }
    
    // Attach context menu to a single message
    function attachContextMenuToMessage(messageElement) {
        // Remove existing listener if any
        const newElement = messageElement.cloneNode(true);
        messageElement.parentNode.replaceChild(newElement, messageElement);
        
        newElement.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const messageId = this.dataset.messageId;
            if (messageId) {
                showContextMenu(e.clientX, e.clientY, messageId, this);
            }
        });
    }
    
    // Close context menu when clicking outside
    document.addEventListener('click', function() {
        hideContextMenu();
    });
    
    // Close context menu on scroll
    const messagesContainer = document.getElementById('chatMessages');
    if (messagesContainer) {
        messagesContainer.addEventListener('scroll', function() {
            hideContextMenu();
        });
    }
    
    // Mark messages as read
    async function markMessagesAsRead(chatId) {
        if (!chatId) return;
        
        // Verify we're still on this chat
        const numChatId = parseInt(chatId);
        const numCurrent = currentChatId ? parseInt(currentChatId) : null;
        if (numCurrent !== numChatId) {
            return; // User switched chats, don't mark as read
        }
        
        // Cancel any ongoing mark as read request
        if (currentMarkAsReadAbortController) {
            currentMarkAsReadAbortController.abort();
        }
        
        // Create new AbortController for this request
        const abortController = new AbortController();
        currentMarkAsReadAbortController = abortController;
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + 'api/mark-messages-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `chat_id=${chatId}`,
                signal: abortController.signal
            });
            
            // Check if request was aborted
            if (abortController.signal.aborted) {
                return;
            }
            
            // Verify we're still on this chat after fetch
            const numCurrentAfterFetch = currentChatId ? parseInt(currentChatId) : null;
            if (numCurrentAfterFetch !== numChatId) {
                return; // User switched chats
            }
            
            const data = await response.json();
            
            // Update unread count after marking as read
            if (data.success) {
                // Update the contact in cache to reflect read status
                const contact = contactsCache.find(c => c.chat_id === numChatId);
                if (contact) {
                    contact.unread_count = 0;
                }
            await updateUnreadCount();
            }
        } catch (error) {
            // Ignore AbortError
            if (error.name !== 'AbortError') {
            console.error('Fehler beim Markieren als gelesen:', error);
            }
        } finally {
            // Clear abort controller if this was the current request
            if (currentMarkAsReadAbortController === abortController) {
                currentMarkAsReadAbortController = null;
            }
        }
    }
    
    // Scroll to bottom
    function scrollToBottom() {
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }
    
    // Collect all media files from current chat
    async function collectMediaFilesFromChat() {
        if (!currentChatId) {
            currentMediaFiles = [];
            return;
        }
        
        const messages = messagesCache.get(currentChatId) || [];
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        const files = [];
        
        // Process messages - no decryption needed
        const messagePromises = messages.map(async (message) => {
            // No decryption - use message as-is
            const decryptedMessage = message.message || '';
            return { message, decryptedMessage };
        });
        
        const processedMessages = await Promise.all(messagePromises);
        
        processedMessages.forEach(({ message, decryptedMessage }) => {
            let messageFiles = [];
            
            // Check if message contains JSON with file data
            if (decryptedMessage && (decryptedMessage.startsWith('[') || decryptedMessage.startsWith('{'))) {
                try {
                    const parsed = JSON.parse(decryptedMessage);
                    if (Array.isArray(parsed)) {
                        // Direct array of files
                        messageFiles = parsed;
                    } else if (parsed && typeof parsed === 'object') {
                        // Check for common keys
                        if (parsed.images && Array.isArray(parsed.images)) {
                            messageFiles = parsed.images;
                        } else if (parsed.files && Array.isArray(parsed.files)) {
                            messageFiles = parsed.files;
                        } else {
                            // Check if any key contains an array of files
                            for (const key in parsed) {
                                if (key !== 'text' && Array.isArray(parsed[key]) && parsed[key].length > 0) {
                                    const firstItem = parsed[key][0];
                                    if (firstItem && (firstItem.data || firstItem.path || firstItem.type)) {
                                        messageFiles = parsed[key];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } catch (e) {
                    // Not JSON, skip
                }
            }
            
            // Also check file_path for files (images and videos)
            if (message.file_path && message.file_type) {
                let filePathFiles = [];
                if (message.file_type === 'multiple' || message.file_path.startsWith('[') || message.file_path.startsWith('{')) {
                    try {
                        const parsedFiles = JSON.parse(message.file_path);
                        if (Array.isArray(parsedFiles)) {
                            filePathFiles = parsedFiles;
                        } else if (parsedFiles && typeof parsedFiles === 'object') {
                            filePathFiles = [parsedFiles];
                        }
                    } catch (e) {
                        filePathFiles = [{
                            path: message.file_path,
                            type: message.file_type,
                            name: ''
                        }];
                    }
                } else {
                    filePathFiles = [{
                        path: message.file_path,
                        type: message.file_type,
                        name: ''
                    }];
                }
                
                // Merge with existing files, avoid duplicates
                filePathFiles.forEach(filePathFile => {
                    const exists = messageFiles.some(f => 
                        (f.path && filePathFile.path && f.path === filePathFile.path) ||
                        (f.data && filePathFile.data && f.data === filePathFile.data)
                    );
                    if (!exists) {
                        messageFiles.push(filePathFile);
                    }
                });
            }
            
            // Add all files from this message
            messageFiles.forEach(file => {
                if (file.data) {
                    // Base64 image
                    files.push({
                        src: file.data,
                        type: 'image',
                        name: file.name || 'Bild'
                    });
                } else if (file.path) {
                    // File path (video or image)
                    const fileUrl = basePath + file.path;
                    const fileType = (file.type || '').toLowerCase();
                    const filePathLower = (file.path || '').toLowerCase();
                    // More robust type detection
                    let isImage = false;
                    if (fileType === 'image') {
                        isImage = true;
                    } else if (fileType === 'video') {
                        isImage = false;
                    } else if (filePathLower.match(/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i)) {
                        isImage = true;
                    } else if (filePathLower.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i)) {
                        isImage = false;
                    } else {
                        // Default to image if uncertain
                        isImage = true;
                    }
                    
                    files.push({
                        src: fileUrl,
                        type: isImage ? 'image' : 'video',
                        name: file.name || 'Datei'
                    });
                }
            });
        });
        
        currentMediaFiles = files;
    }
    
    // Initialize media lightbox
    function initMediaLightbox() {
        const lightbox = document.getElementById('chatMediaLightbox');
        const lightboxImage = document.getElementById('chatMediaLightboxImage');
        const lightboxVideo = document.getElementById('chatMediaLightboxVideo');
        const lightboxClose = document.getElementById('chatMediaLightboxClose');
        const lightboxPrev = document.getElementById('chatMediaLightboxPrev');
        const lightboxNext = document.getElementById('chatMediaLightboxNext');
        const lightboxCounter = document.getElementById('chatMediaLightboxCounter');
        const lightboxBackdrop = lightbox?.querySelector('.chat-media-lightbox-backdrop');
        
        if (!lightbox || !lightboxImage || !lightboxVideo || !lightboxClose || !lightboxPrev || !lightboxNext) return;
        
        // Show media at specific index
        function showMediaAtIndex(index) {
            if (currentMediaFiles.length === 0 || index < 0 || index >= currentMediaFiles.length) {
                return;
            }
            
            const media = currentMediaFiles[index];
            currentMediaIndex = index;
            
            // Update counter
            if (lightboxCounter && currentMediaFiles.length > 1) {
                lightboxCounter.textContent = `${index + 1} / ${currentMediaFiles.length}`;
                lightboxCounter.style.display = 'block';
            } else if (lightboxCounter) {
                lightboxCounter.style.display = 'none';
            }
            
            // Update navigation buttons
            lightboxPrev.disabled = index === 0;
            lightboxNext.disabled = index === currentMediaFiles.length - 1;
            
            if (media.type === 'image') {
                lightboxImage.src = media.src;
                lightboxImage.style.display = 'block';
                lightboxVideo.style.display = 'none';
                lightboxVideo.pause();
                lightboxVideo.src = '';
            } else {
                lightboxVideo.src = media.src;
                lightboxVideo.style.display = 'block';
                lightboxImage.style.display = 'none';
                lightboxVideo.play().catch(err => console.log('Video play failed:', err));
            }
        }
        
        // Navigate to previous media
        function showPreviousMedia() {
            if (currentMediaIndex > 0) {
                showMediaAtIndex(currentMediaIndex - 1);
            }
        }
        
        // Navigate to next media
        function showNextMedia() {
            if (currentMediaIndex < currentMediaFiles.length - 1) {
                showMediaAtIndex(currentMediaIndex + 1);
            }
        }
        
        // Close lightbox function
        function closeLightbox() {
            lightbox.style.display = 'none';
            lightboxImage.style.display = 'none';
            lightboxVideo.style.display = 'none';
            lightboxVideo.pause();
            lightboxVideo.src = '';
            lightboxImage.src = '';
        }
        
        // Open lightbox with specific media
        async function openLightboxWithMedia(src, type) {
            // Collect all media files from current chat
            await collectMediaFilesFromChat();
            
            if (currentMediaFiles.length === 0) return;
            
            // Find the index of the clicked media
            const index = currentMediaFiles.findIndex(media => media.src === src);
            const startIndex = index >= 0 ? index : 0;
            
            showMediaAtIndex(startIndex);
            lightbox.style.display = 'flex';
        }
        
        // Close button
        lightboxClose.addEventListener('click', closeLightbox);
        
        // Navigation buttons
        lightboxPrev.addEventListener('click', showPreviousMedia);
        lightboxNext.addEventListener('click', showNextMedia);
        
        // Close on backdrop click
        if (lightboxBackdrop) {
            lightboxBackdrop.addEventListener('click', closeLightbox);
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (lightbox.style.display === 'none') return;
            
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                showPreviousMedia();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                showNextMedia();
            }
        });
        
        // Use event delegation for images and videos in messages
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.addEventListener('click', async function(e) {
                // Check if clicked element is an image or video
                const img = e.target.closest('.chat-message-image');
                const video = e.target.closest('.chat-message-video');
                
                // Don't open if clicking on video controls
                if (e.target.tagName === 'BUTTON' || e.target.closest('button') || 
                    e.target.tagName === 'INPUT' || e.target.closest('input') ||
                    e.target.tagName === 'PROGRESS' || e.target.closest('progress')) {
                    return;
                }
                
                // Always open lightbox with all files from chat when clicking any image/video
                if (img && img.src) {
                    e.preventDefault();
                    e.stopPropagation();
                    await openLightboxWithMedia(img.src, 'image');
                } else if (video && video.src) {
                    // Check if click is directly on video element (not on controls)
                    const rect = video.getBoundingClientRect();
                    const clickY = e.clientY - rect.top;
                    const videoHeight = rect.height;
                    const isInControlsArea = clickY > videoHeight * 0.7;
                    
                    if (!isInControlsArea || e.detail === 2) {
                        e.preventDefault();
                        e.stopPropagation();
                        await openLightboxWithMedia(video.src, 'video');
                    }
                }
            });
        }
    }
    
    // Encryption removed
    
    // Helper functions
    // Helper function to parse timestamp
    function parseTimestamp(timestamp) {
        if (!timestamp) return null;
        
        let date;
        if (typeof timestamp === 'string') {
            // MySQL timestamp format: YYYY-MM-DD HH:MM:SS
            if (timestamp.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)) {
                date = new Date(timestamp.replace(' ', 'T') + 'Z');
            } else {
                date = new Date(timestamp);
            }
        } else {
            date = new Date(timestamp);
        }
        
        if (isNaN(date.getTime())) {
            return null;
        }
        
        return date;
    }
    
    // Format date for date separators (Heute, Gestern, etc.)
    function formatDate(timestamp) {
        const date = parseTimestamp(timestamp);
        if (!date) return '';
        
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        
        if (messageDate.getTime() === today.getTime()) {
            return 'Heute';
        } else if (messageDate.getTime() === yesterday.getTime()) {
            return 'Gestern';
        } else {
            // Show date in German format
            const diffDays = Math.floor((today - messageDate) / (1000 * 60 * 60 * 24));
            if (diffDays < 7) {
                // Show weekday for this week
                const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
                return weekdays[date.getDay()];
            } else {
                // Show full date
                return date.toLocaleDateString('de-DE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }
        }
    }
    
    // Check if date changed between two messages
    function isSameDay(timestamp1, timestamp2) {
        const date1 = parseTimestamp(timestamp1);
        const date2 = parseTimestamp(timestamp2);
        if (!date1 || !date2) return true;
        
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }
    
    function formatTime(timestamp) {
        if (!timestamp) return '';
        
        const date = parseTimestamp(timestamp);
        if (!date) return '';
        
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        
        if (diff < 60000) { // Less than 1 minute
            return 'Gerade eben';
        } else if (diff < 3600000) { // Less than 1 hour
            const minutes = Math.floor(diff / 60000);
            return `vor ${minutes} Min`;
        } else if (diff < 86400000) { // Less than 1 day
            const hours = Math.floor(diff / 3600000);
            return `vor ${hours} Std`;
        } else if (diff < 604800000) { // Less than 1 week
            const days = Math.floor(diff / 86400000);
            return `vor ${days} Tag${days > 1 ? 'en' : ''}`;
        } else {
            // Show full date for older messages
            return date.toLocaleDateString('de-DE', { 
                day: '2-digit', 
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Extract files from a message
    function extractFilesFromMessage(message, decryptedMessage) {
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        let files = [];
        
        // First check if message contains JSON with file data (base64 images or videos)
        if (decryptedMessage && (decryptedMessage.startsWith('[') || decryptedMessage.startsWith('{'))) {
            try {
                const parsed = JSON.parse(decryptedMessage);
                if (Array.isArray(parsed)) {
                    files = parsed;
                } else if (parsed && typeof parsed === 'object') {
                    if (parsed.images && Array.isArray(parsed.images)) {
                        files = parsed.images;
                    } else {
                        // Check other keys for file arrays
                        for (const key in parsed) {
                            if (key !== 'text' && Array.isArray(parsed[key]) && parsed[key].length > 0) {
                                const firstItem = parsed[key][0];
                                if (firstItem && (firstItem.data || firstItem.path || firstItem.type)) {
                                    files = parsed[key];
                                    break;
                                }
                            }
                        }
                    }
                }
            } catch (e) {
                // Not JSON
            }
        }
        
        // Also check file_path for files
        if (message.file_path && message.file_type) {
            let filePathFiles = [];
            
            if (message.file_type === 'multiple' || message.file_path.startsWith('[') || message.file_path.startsWith('{')) {
                try {
                    const parsedFiles = JSON.parse(message.file_path);
                    if (Array.isArray(parsedFiles)) {
                        filePathFiles = parsedFiles;
                    } else if (parsedFiles && typeof parsedFiles === 'object') {
                        filePathFiles = [parsedFiles];
                    }
                } catch (e) {
                    filePathFiles = [{
                        path: message.file_path,
                        type: message.file_type,
                        name: ''
                    }];
                }
            } else {
                filePathFiles = [{
                    path: message.file_path,
                    type: message.file_type,
                    name: ''
                }];
            }
            
            // Merge file_path files with existing files, avoid duplicates
            filePathFiles.forEach(filePathFile => {
                const exists = files.some(f => 
                    (f.path && filePathFile.path && f.path === filePathFile.path) ||
                    (f.data && filePathFile.data && f.data === filePathFile.data)
                );
                if (!exists) {
                    files.push(filePathFile);
                }
            });
        }
        
        return files;
    }
    
        // Generate message content with file group (for WhatsApp-style grid with +X overlay)
    function generateMessageContentWithFileGroup(message, decryptedMessage, allFiles, fileGroup) {
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        let content = '';
        
        // Extract text content
        let hasTextMessage = false;
        let textContent = decryptedMessage;
        
        if (decryptedMessage && (decryptedMessage.startsWith('[') || decryptedMessage.startsWith('{'))) {
            try {
                const parsed = JSON.parse(decryptedMessage);
                if (parsed && typeof parsed === 'object' && parsed.text && parsed.text.trim()) {
                    hasTextMessage = true;
                    textContent = parsed.text;
                } else {
                    textContent = '';
                }
            } catch (e) {
                if (decryptedMessage.trim() && !decryptedMessage.startsWith('data:')) {
                    hasTextMessage = true;
                    textContent = decryptedMessage;
                }
            }
        } else if (decryptedMessage && decryptedMessage.trim() && !decryptedMessage.startsWith('data:')) {
            hasTextMessage = true;
            textContent = decryptedMessage;
        }
        
        // Also check other messages in the group for text
        if (!hasTextMessage && fileGroup && fileGroup.messages) {
            for (const groupMsg of fileGroup.messages) {
                const msgText = groupMsg.decryptedMessage;
                if (msgText && msgText.trim() && !msgText.startsWith('[') && !msgText.startsWith('{') && !msgText.startsWith('data:')) {
                    hasTextMessage = true;
                    textContent = msgText;
                    break;
                }
            }
        }
        
        // Render files in grid
        if (allFiles && allFiles.length > 0) {
            const fileCount = allFiles.length;
            const maxVisible = 4;
            const remainingCount = fileCount - maxVisible;
            const filesToShow = allFiles.slice(0, maxVisible);
            
            // Prepare file URLs and types for data attribute
            const fileData = allFiles.map(file => {
                let fileUrl = '';
                let fileType = 'image';
                
                if (file.data) {
                    fileUrl = file.data;
                    fileType = 'image';
                } else if (file.path) {
                    fileUrl = basePath + file.path;
                    const fileTypeLower = (file.type || '').toLowerCase();
                    const filePathLower = (file.path || '').toLowerCase();
                    // More robust type detection: check type field first, then file extension
                    if (fileTypeLower === 'image') {
                        fileType = 'image';
                    } else if (fileTypeLower === 'video') {
                        fileType = 'video';
                    } else if (filePathLower.match(/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i)) {
                        // Image extension found
                        fileType = 'image';
                    } else if (filePathLower.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i)) {
                        // Video extension found
                        fileType = 'video';
                    } else {
                        // Default to image if uncertain (safer for most cases)
                        fileType = 'image';
                    }
                }
                
                return { url: fileUrl, type: fileType };
            }).filter(f => f.url);
            
            // Only render if we have valid file URLs
            if (fileData.length > 0) {
                // Store file data as JSON in data attribute
                // Escape JSON for HTML attribute by escaping quotes
                const fileDataJson = JSON.stringify(fileData).replace(/"/g, '&quot;');
                
                // Determine grid columns based on number of files
                let gridColumns = 2; // Default to 2 columns
                let gridMaxWidth = '300px'; // Default max width
                
                if (fileCount === 1) {
                    // Single file - no grid, just show the file directly
                    const file = allFiles[0];
                    let fileUrl = '';
                    let isImage = false;
                    
                    if (file.data) {
                        fileUrl = escapeHtml(file.data);
                        isImage = true;
                    } else if (file.path) {
                        fileUrl = basePath + escapeHtml(file.path);
                        const fileType = (file.type || '').toLowerCase();
                        const filePathLower = (file.path || '').toLowerCase();
                        // More robust type detection
                        if (fileType === 'image') {
                            isImage = true;
                        } else if (fileType === 'video') {
                            isImage = false;
                        } else if (filePathLower.match(/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i)) {
                            isImage = true;
                        } else if (filePathLower.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i)) {
                            isImage = false;
                        } else {
                            // Default to image if uncertain
                            isImage = true;
                        }
                    }
                    
                    if (fileUrl) {
                        content += '<div class="chat-message-media">';
                        if (isImage) {
                            content += `<img src="${fileUrl}" alt="Bild" class="chat-message-image" loading="lazy" style="cursor: pointer;" data-file-group-data="${fileDataJson}" data-file-index="0" data-all-files-count="1">`;
                        } else {
                            content += `<video src="${fileUrl}" controls class="chat-message-video" preload="metadata" style="cursor: pointer;" data-file-group-data="${fileDataJson}" data-file-index="0" data-all-files-count="1">
                                Ihr Browser unterstützt das Video-Tag nicht.
                            </video>`;
                        }
                        content += '</div>';
                    }
                } else {
                    // Multiple files - use grid
                    // Determine columns based on visible files count
                    const visibleCount = Math.min(fileCount, maxVisible);
                    if (visibleCount === 2) {
                        gridColumns = 2;
                        gridMaxWidth = '200px'; // 2 files: smaller width
                    } else if (visibleCount === 3) {
                        gridColumns = 2;
                        gridMaxWidth = '200px'; // 3 files: 2x2 grid with one empty
                    } else {
                        gridColumns = 2;
                        gridMaxWidth = '300px'; // 4+ files: full 2x2 grid
                    }
                    
                    content += `<div class="chat-message-media-grid" style="grid-template-columns: repeat(${gridColumns}, 1fr); max-width: ${gridMaxWidth};">`;
                    
                    filesToShow.forEach((file, idx) => {
                        const isLastVisible = idx === maxVisible - 1;
                        const showOverlay = remainingCount > 0 && isLastVisible;
                        
                        let fileUrl = '';
                        let isImage = false;
                        
                        if (file.data) {
                            fileUrl = escapeHtml(file.data);
                            isImage = true;
                        } else if (file.path) {
                            fileUrl = basePath + escapeHtml(file.path);
                            const fileType = (file.type || '').toLowerCase();
                            const filePathLower = (file.path || '').toLowerCase();
                            // More robust type detection
                            if (fileType === 'image') {
                                isImage = true;
                            } else if (fileType === 'video') {
                                isImage = false;
                            } else if (filePathLower.match(/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i)) {
                                isImage = true;
                            } else if (filePathLower.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i)) {
                                isImage = false;
                            } else {
                                // Default to image if uncertain
                                isImage = true;
                            }
                        }
                        
                        if (fileUrl) {
                            content += `<div class="chat-message-media-item ${showOverlay ? 'has-overlay' : ''}" 
                                data-file-group-data="${fileDataJson}" 
                                data-file-index="${idx}" 
                                data-all-files-count="${allFiles.length}"
                                style="cursor: pointer;">`;
                            
                            if (isImage) {
                                content += `<img src="${fileUrl}" alt="Bild" class="chat-message-image" loading="lazy">`;
                            } else {
                                content += `<video src="${fileUrl}" controls class="chat-message-video" preload="metadata">
                                    Ihr Browser unterstützt das Video-Tag nicht.
                                </video>`;
                            }
                            
                            if (showOverlay) {
                                content += `<div class="chat-message-media-overlay">
                                    <span class="chat-message-media-overlay-text">+${remainingCount}</span>
                                </div>`;
                            }
                            
                            content += '</div>';
                        }
                    });
                    
                    content += '</div>';
                }
            }
        }
        
        // Add text message if present
        if (hasTextMessage && textContent && textContent.trim() && !textContent.startsWith('data:')) {
            content += `<div class="chat-message-text">${escapeHtml(textContent)}</div>`;
        }
        
        // Return content - don't show "Datei" placeholder
        return content;
    }
    
    // Generate message content (text, image, or video)
    function generateMessageContent(message, decryptedMessage, skipFiles = false) {
        let content = '';
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        
        // Prüfe ob es eine Anfrage-Nachricht ist
        if (message.file_type === 'anfrage_request' && message.anfrage_data) {
            return generateAnfrageMessageContent(message, message.anfrage_data);
        }
        
        let files = [];
        let hasTextMessage = false;
        
        
        // First check if message contains JSON with file data (base64 images or videos)
        let textContent = decryptedMessage;
        
        if (decryptedMessage && (decryptedMessage.startsWith('[') || decryptedMessage.startsWith('{'))) {
            try {
                const parsed = JSON.parse(decryptedMessage);
                if (Array.isArray(parsed)) {
                    // Array of files (images or videos or mixed)
                    files = parsed;
                    hasTextMessage = false;
                    textContent = '';
                } else if (parsed && typeof parsed === 'object') {
                    // Object with text and files
                    // Check for 'images' key first (for images)
                    if (parsed.images && Array.isArray(parsed.images)) {
                        files = parsed.images;
                    }
                    // Also check if any other key contains an array of files
                    // This handles cases where the server sends all files in a different key
                    if (files.length === 0) {
                        for (const key in parsed) {
                            if (key !== 'text' && Array.isArray(parsed[key]) && parsed[key].length > 0) {
                                // Check if it looks like file data (has data, path, or type property)
                                const firstItem = parsed[key][0];
                                if (firstItem && (firstItem.data || firstItem.path || firstItem.type)) {
                                    files = parsed[key];
                                    break;
                                }
                            }
                        }
                    }
                    if (parsed.text && parsed.text.trim()) {
                        hasTextMessage = true;
                        textContent = parsed.text; // Use text for display
                    } else {
                        hasTextMessage = false;
                        textContent = '';
                    }
                }
            } catch (e) {
                // Not JSON, treat as text message
                hasTextMessage = true;
                textContent = decryptedMessage;
            }
        } else if (decryptedMessage && decryptedMessage.trim() && !decryptedMessage.startsWith('data:')) {
            hasTextMessage = true;
            textContent = decryptedMessage;
        }
        
        // Also check file_path for files (images and videos) or old format
        if (message.file_path && message.file_type) {
            let filePathFiles = [];
            
            // Check if it's multiple files (JSON in file_path)
            if (message.file_type === 'multiple' || message.file_path.startsWith('[') || message.file_path.startsWith('{')) {
                try {
                    const parsedFiles = JSON.parse(message.file_path);
                    if (Array.isArray(parsedFiles)) {
                        filePathFiles = parsedFiles;
                    } else if (parsedFiles && typeof parsedFiles === 'object') {
                        // Single object, convert to array
                        filePathFiles = [parsedFiles];
                    }
                } catch (e) {
                    console.error('Error parsing file_path JSON:', e);
                    // Fallback: treat as single file path
                    filePathFiles = [{
                        path: message.file_path,
                        type: message.file_type,
                        name: ''
                    }];
                }
            } else {
                // Single file
                filePathFiles = [{
                    path: message.file_path,
                    type: message.file_type,
                    name: ''
                }];
            }
            
            // Merge file_path files with existing files
            // Avoid duplicates by checking if path already exists
            filePathFiles.forEach(filePathFile => {
                const exists = files.some(f => 
                    (f.path && filePathFile.path && f.path === filePathFile.path) ||
                    (f.data && filePathFile.data && f.data === filePathFile.data)
                );
                if (!exists) {
                    files.push(filePathFile);
                }
            });
            
        }
        
        if (!skipFiles && files.length > 0) {
            // If multiple files, show in grid
            if (files.length > 1) {
                // Show each file separately (no grid)
                files.forEach(file => {
                    if (file.data) {
                        // Base64 image from message field
                        content += `<div class="chat-message-media">
                            <img src="${escapeHtml(file.data)}" alt="Bild" class="chat-message-image" loading="lazy" style="cursor: pointer;">
                        </div>`;
                    } else if (file.path) {
                        // File from file_path (image or video)
                        const fileUrl = basePath + escapeHtml(file.path);
                        const fileType = (file.type || '').toLowerCase();
                        const filePathLower = (file.path || '').toLowerCase();
                        
                        // Determine if it's an image or video based on type or file extension
                        let isImage = false;
                        if (fileType === 'image') {
                            isImage = true;
                        } else if (fileType === 'video') {
                            isImage = false;
                        } else if (filePathLower.match(/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i)) {
                            isImage = true;
                        } else if (filePathLower.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i)) {
                            isImage = false;
                        } else {
                            // Default to image if uncertain (safer for most cases)
                            isImage = true;
                        }
                        
                        if (isImage) {
                            content += `<div class="chat-message-media">
                                <img src="${fileUrl}" alt="Bild" class="chat-message-image" loading="lazy" style="cursor: pointer;">
                            </div>`;
                        } else {
                            // Default to video for anything that's not clearly an image
                            content += `<div class="chat-message-media">
                                <video src="${fileUrl}" controls class="chat-message-video" preload="metadata" style="cursor: pointer;">
                                    Ihr Browser unterstützt das Video-Tag nicht.
                                </video>
                            </div>`;
                        }
                    }
                });
            } else {
                // Single file
                const file = files[0];
                
                if (file.data) {
                    // Base64 image from message field
                content += `<div class="chat-message-media">
                        <img src="${escapeHtml(file.data)}" alt="Bild" class="chat-message-image" loading="lazy" style="cursor: pointer;">
                </div>`;
                } else if (file.path) {
                    // File from file_path (image or video)
                    const fileUrl = basePath + escapeHtml(file.path);
                    const fileType = (file.type || '').toLowerCase();
                    const filePathLower = (file.path || '').toLowerCase();
                    
                    // Determine if it's an image or video based on type or file extension
                    let isImage = false;
                    if (fileType === 'image') {
                        isImage = true;
                    } else if (fileType === 'video') {
                        isImage = false;
                    } else if (filePathLower.match(/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i)) {
                        isImage = true;
                    } else if (filePathLower.match(/\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)$/i)) {
                        isImage = false;
                    } else {
                        // Default to image if uncertain (safer for most cases)
                        isImage = true;
                    }
                    
                    if (isImage) {
                content += `<div class="chat-message-media">
                            <img src="${fileUrl}" alt="Bild" class="chat-message-image" loading="lazy" style="cursor: pointer;">
                        </div>`;
                    } else {
                        // Default to video for anything that's not clearly an image
                        content += `<div class="chat-message-media">
                            <video src="${fileUrl}" controls class="chat-message-video" preload="metadata" style="cursor: pointer;">
                        Ihr Browser unterstützt das Video-Tag nicht.
                    </video>
                </div>`;
                    }
                }
            }
        }
        
        // Add text message if present (and not file data)
        if (hasTextMessage && textContent && textContent.trim() && !textContent.startsWith('data:')) {
            content += `<div class="chat-message-text">${escapeHtml(textContent)}</div>`;
        }
        
        // Return content - don't show "Datei" placeholder
        return content;
    }
    
    // Public API for creating chat from contact button
    window.createChatFromContact = async function(userId, username, angebotTitle = null, angebotId = null) {
        if (!chatBox || chatBox.classList.contains('hidden')) {
            showChatBox();
        }
        
        // Check if chat already exists in contacts cache
        const numUserId = parseInt(userId);
        const existingContact = contactsCache.find(c => c.user_id === numUserId);
        let chatId = null;
        
        if (existingContact && existingContact.chat_id) {
            // Chat exists and has messages - open it and switch to contacts view
            chatId = existingContact.chat_id;
            await openChat(chatId, numUserId, username, false);
            // Switch to contacts view since this is an existing contact with messages
            switchView('contacts');
        } else {
            // No existing chat or chat has no messages yet
            // Create new chat but keep current view (don't force switch to contacts)
            chatId = await createNewChat(numUserId, username, false);
        }
        
        // Wenn ein Angebot vorhanden ist, erstelle eine Anfrage-Nachricht
        if (chatId && angebotId && angebotTitle) {
            await createAnfrageFromChat(chatId, numUserId, angebotId, angebotTitle);
        }
    };
    
    // Erstelle Anfrage-Nachricht im Chat
    async function createAnfrageFromChat(chatId, receiverId, angebotId, angebotTitle) {
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const formData = new FormData();
            formData.append('chat_id', chatId);
            formData.append('receiver_id', receiverId);
            formData.append('angebot_id', angebotId);
            
            const response = await fetch(basePath + 'api/create-anfrage-from-chat.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                // Nachricht erfolgreich gesendet - lade Nachrichten neu
                if (currentChatId === chatId) {
                    await loadMessages(chatId, false);
                }
                // Lade Kontakte neu, damit der Chat in "Kontakte" erscheint
                loadContacts();
                loadNewContacts();
                // Update profile stats if function exists
                if (typeof window.updateProfileStats === 'function') {
                    window.updateProfileStats();
                }
            } else {
                alert(data.message || 'Fehler beim Erstellen der Anfrage');
            }
        } catch (error) {
            console.error('Fehler beim Erstellen der Anfrage:', error);
            alert('Fehler beim Erstellen der Anfrage');
        }
    }
    
    // Rendere Anfrage-Nachricht mit Buttons
    function generateAnfrageMessageContent(message, anfrageData) {
        const isSent = message.is_sent;
        const anfrageId = anfrageData.anfrage_id;
        const status = anfrageData.status || 'pending';
        const angebotTitle = anfrageData.angebot_title || '';
        const messageType = anfrageData.type || (message.file_type === 'anfrage_accepted' ? 'anfrage_accepted' : 'anfrage_request');
        const isRequester = !isSent; // Empfänger ist der Hilfesuchende
        const isHelper = isSent; // Sender ist der Hilfegebende
        
        let messageText = message.message || '';
        let buttonHtml = '';
        let messageClass = '';
        
        // Handle anfrage_accepted type (confirmation message sent to helper)
        if (messageType === 'anfrage_accepted') {
            if (isHelper) {
                // Helper sees the acceptance message - show confirm/reject buttons
                if (status === 'accepted') {
                    buttonHtml = `
                        <div class="anfrage-buttons">
                            <button class="anfrage-btn anfrage-btn-confirm" data-anfrage-id="${anfrageId}" data-action="confirm">
                                Bestätigen
                            </button>
                            <button class="anfrage-btn anfrage-btn-reject" data-anfrage-id="${anfrageId}" data-action="cancel">
                                Ablehnen
                            </button>
                        </div>
                    `;
                    messageClass = 'anfrage-accepted';
                } else if (status === 'confirmed') {
                    messageClass = 'anfrage-confirmed';
                    messageText = messageText + '<div class="anfrage-status-badge anfrage-status-confirmed">Hilfe bestätigt</div>';
                }
            } else {
                // Requester sees their own acceptance message - show status
                messageClass = 'anfrage-accepted';
                messageText = messageText + '<div class="anfrage-status-badge anfrage-status-accepted">Anfrage angenommen</div>';
            }
        } else {
            // Original anfrage_request type
            if (status === 'rejected') {
                messageClass = 'anfrage-rejected';
            } else if (status === 'accepted') {
                messageClass = 'anfrage-accepted';
                if (isRequester) {
                    // Hilfesuchender hat bereits angenommen - zeige Status
                    messageText = messageText + '<div class="anfrage-status-badge anfrage-status-accepted">Anfrage angenommen</div>';
                } else if (isHelper) {
                    // Hilfegebender sieht, dass angenommen wurde - zeige Bestätigungs-Buttons
                    buttonHtml = `
                        <div class="anfrage-buttons">
                            <button class="anfrage-btn anfrage-btn-confirm" data-anfrage-id="${anfrageId}" data-action="confirm">
                                Bestätigen
                            </button>
                            <button class="anfrage-btn anfrage-btn-reject" data-anfrage-id="${anfrageId}" data-action="cancel">
                                Ablehnen
                            </button>
                        </div>
                    `;
                }
            } else if (status === 'confirmed') {
                messageClass = 'anfrage-confirmed';
                messageText = messageText + '<div class="anfrage-status-badge anfrage-status-confirmed">Hilfe bestätigt</div>';
            } else if (status === 'pending') {
                if (isRequester) {
                    // Hilfesuchender sieht Anfrage - zeige Annehmen/Ablehnen Buttons
                    buttonHtml = `
                        <div class="anfrage-buttons">
                            <button class="anfrage-btn anfrage-btn-accept" data-anfrage-id="${anfrageId}" data-action="accept">
                                Annehmen
                            </button>
                            <button class="anfrage-btn anfrage-btn-reject" data-anfrage-id="${anfrageId}" data-action="reject">
                                Ablehnen
                            </button>
                        </div>
                    `;
                } else {
                    // Hilfegebender sieht seine eigene Anfrage - zeige Status
                    messageText = messageText + '<div class="anfrage-status-badge anfrage-status-pending">Warte auf Antwort</div>';
                }
            }
        }
        
        // messageText kann bereits HTML enthalten (Status-Badges)
        const textContent = messageText.includes('<div') ? messageText : escapeHtml(messageText);
        
        // Hole Kategorie-Farbe aus anfrageData
        const categoryColor = anfrageData.category_color || '#607D8B';
        const categoryStyle = `border-left: 4px solid ${categoryColor}; background: linear-gradient(to right, ${categoryColor}15, var(--message-bg, #f0f0f0));`;
        
        return `
            <div class="chat-message-anfrage ${messageClass}" style="${categoryStyle}">
                <div class="chat-message-text">${textContent}</div>
                ${buttonHtml}
            </div>
        `;
    }
    
    // Event-Handler für Anfrage-Buttons
    function attachAnfrageButtonListeners() {
        const buttons = document.querySelectorAll('.anfrage-btn');
        buttons.forEach(btn => {
            // Prüfe ob Listener bereits angehängt wurde
            if (btn.dataset.listenerAttached === 'true') {
                return;
            }
            btn.dataset.listenerAttached = 'true';
            
            btn.addEventListener('click', async function(e) {
                e.stopPropagation();
                const anfrageId = parseInt(this.dataset.anfrageId);
                const action = this.dataset.action;
                
                if (!anfrageId || !action) return;
                
                // Deaktiviere Button während der Verarbeitung
                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Wird verarbeitet...';
                
                try {
                    const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                    const formData = new FormData();
                    formData.append('anfrage_id', anfrageId);
                    formData.append('action', action);
                    
                    const response = await fetch(basePath + 'api/handle-anfrage.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        // If confirm action, show status bar
                        if (action === 'confirm' && data.anfrage_id && data.angebot_title) {
                            showAnfrageStatusBar(data.anfrage_id, data.angebot_title);
                        }
                        
                        // Lade Nachrichten neu, um aktualisierten Status zu sehen
                        if (currentChatId) {
                            await loadMessages(currentChatId, false);
                        }
                        // Update profile stats if function exists
                        if (typeof window.updateProfileStats === 'function') {
                            window.updateProfileStats();
                        }
                    } else {
                        alert(data.message || 'Fehler beim Verarbeiten der Anfrage');
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                } catch (error) {
                    console.error('Fehler:', error);
                    alert('Fehler beim Verarbeiten der Anfrage');
                    this.disabled = false;
                    this.textContent = originalText;
                }
            });
        });
    }
    
    // Funktionen für Status-Bar
    function showAnfrageStatusBar(anfrageId, angebotTitle) {
        const statusBar = document.getElementById('chatAnfrageStatusBar');
        const statusText = document.getElementById('chatAnfrageStatusText');
        
        if (statusBar && statusText) {
            statusText.textContent = angebotTitle;
            statusBar.dataset.anfrageId = anfrageId;
            statusBar.style.display = 'flex';
            
            // Attach event listeners if not already attached
            attachStatusBarListeners();
        }
    }
    
    function hideAnfrageStatusBar() {
        const statusBar = document.getElementById('chatAnfrageStatusBar');
        if (statusBar) {
            statusBar.style.display = 'none';
            delete statusBar.dataset.anfrageId;
        }
    }
    
    function attachStatusBarListeners() {
        const erledigtBtn = document.getElementById('chatAnfrageStatusBtnErledigt');
        const abbrechenBtn = document.getElementById('chatAnfrageStatusBtnAbbrechen');
        const statusBar = document.getElementById('chatAnfrageStatusBar');
        
        if (!statusBar || !statusBar.dataset.listenerAttached) {
            statusBar.dataset.listenerAttached = 'true';
            
            if (erledigtBtn) {
                erledigtBtn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    const anfrageId = parseInt(statusBar.dataset.anfrageId);
                    if (!anfrageId) return;
                    
                    this.disabled = true;
                    const originalText = this.textContent;
                    this.textContent = 'Wird verarbeitet...';
                    
                    try {
                        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                        const formData = new FormData();
                        formData.append('anfrage_id', anfrageId);
                        formData.append('action', 'erledigt');
                        
                        const response = await fetch(basePath + 'api/handle-anfrage.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            hideAnfrageStatusBar();
                            if (currentChatId) {
                                await loadMessages(currentChatId, false);
                            }
                            if (typeof window.updateProfileStats === 'function') {
                                window.updateProfileStats();
                            }
                        } else {
                            alert(data.message || 'Fehler beim Markieren als erledigt');
                            this.disabled = false;
                            this.textContent = originalText;
                        }
                    } catch (error) {
                        console.error('Fehler:', error);
                        alert('Fehler beim Markieren als erledigt');
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                });
            }
            
            if (abbrechenBtn) {
                abbrechenBtn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    const anfrageId = parseInt(statusBar.dataset.anfrageId);
                    if (!anfrageId) return;
                    
                    this.disabled = true;
                    const originalText = this.textContent;
                    this.textContent = 'Wird verarbeitet...';
                    
                    try {
                        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                        const formData = new FormData();
                        formData.append('anfrage_id', anfrageId);
                        formData.append('action', 'cancel');
                        
                        const response = await fetch(basePath + 'api/handle-anfrage.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            hideAnfrageStatusBar();
                            if (currentChatId) {
                                await loadMessages(currentChatId, false);
                            }
                            if (typeof window.updateProfileStats === 'function') {
                                window.updateProfileStats();
                            }
                        } else {
                            alert(data.message || 'Fehler beim Abbrechen');
                            this.disabled = false;
                            this.textContent = originalText;
                        }
                    } catch (error) {
                        console.error('Fehler:', error);
                        alert('Fehler beim Abbrechen');
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                });
            }
        }
    }
    
    // Check for confirmed anfragen when opening a chat
    async function checkAndShowStatusBar(chatId) {
        if (!chatId) {
            hideAnfrageStatusBar();
            return;
        }
        
        try {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            const response = await fetch(basePath + 'api/get-chat-messages.php?chat_id=' + chatId);
            const data = await response.json();
            
            if (data.success && data.messages && data.current_user_id) {
                const currentUserId = parseInt(data.current_user_id);
                
                // Find confirmed anfrage message where current user is the helper
                for (const msg of data.messages) {
                    if (msg.anfrage_data && msg.anfrage_data.status === 'confirmed') {
                        // Check if current user is the helper
                        const helperId = msg.anfrage_data.helper_id;
                        if (helperId && parseInt(helperId) === currentUserId) {
                            // Current user is the helper - show status bar
                            const anfrageId = msg.anfrage_data.anfrage_id;
                            const angebotTitle = msg.anfrage_data.angebot_title || '';
                            
                            if (anfrageId && angebotTitle) {
                                showAnfrageStatusBar(anfrageId, angebotTitle);
                                return;
                            }
                        }
                    }
                }
            }
            
            // No confirmed anfrage found, hide status bar
            hideAnfrageStatusBar();
        } catch (error) {
            console.error('Fehler beim Prüfen der Status-Bar:', error);
            hideAnfrageStatusBar();
        }
    }
    
    // Make functions globally available
    window.showAnfrageStatusBar = showAnfrageStatusBar;
    window.hideAnfrageStatusBar = hideAnfrageStatusBar;
    window.checkAndShowStatusBar = checkAndShowStatusBar;
})();




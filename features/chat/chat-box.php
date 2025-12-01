<?php
if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    return;
}
?>
<div id="chatBox" class="chat-box hidden">
    <div class="chat-box-header">
        <h3 class="chat-box-title">Chat</h3>
        <div class="chat-box-header-actions">
            <button class="chat-box-btn chat-minimize-btn" id="chatMinimizeBtn" aria-label="Minimieren">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>
            <button class="chat-box-btn chat-close-btn" id="chatCloseBtn" aria-label="Schließen">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
    <div class="chat-box-content">
        <div class="chat-contacts-panel">
            <div class="chat-contacts-header" id="chatContactsHeader">
                <button class="chat-contacts-tab active" data-view="contacts" id="chatContactsTab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Kontakte</span>
                </button>
                <button class="chat-contacts-tab" data-view="new-contacts" id="chatNewContactsTab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    <span>Neue Kontakte</span>
                </button>
            </div>
            <div class="chat-search-container" id="chatSearchContainer">
                <input type="text" class="chat-search-input" id="chatSearchInput" placeholder="Nach Kontakten suchen...">
            </div>
            <div class="chat-contacts-content">
                <div class="chat-contacts-list" id="chatContactsList">
                    <div class="chat-loading">Lade Kontakte...</div>
                </div>
                <div class="chat-new-contacts-list" id="chatNewContactsList" style="display: none;">
                    <div class="chat-loading">Lade Kontakte...</div>
                </div>
            </div>
        </div>
        <div class="chat-messages-panel">
            <div class="chat-empty-state" id="chatEmptyState">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <p>Wähle einen Chat aus, um anzufangen zu chatten</p>
            </div>
            <div class="chat-messages-header" id="chatMessagesHeader" style="display: none;">
                <div class="chat-messages-user-info">
                    <img class="chat-user-avatar" id="chatUserAvatar" src="" alt="">
                    <div class="chat-user-details">
                        <h4 class="chat-user-name" id="chatUserName"></h4>
                        <span class="chat-user-status" id="chatUserStatus"></span>
                    </div>
                </div>
            </div>
            <div class="chat-messages-container" id="chatMessagesContainer" style="display: none;">
                <div class="chat-messages" id="chatMessages"></div>
                
                <!-- Media Lightbox within chat area -->
                <div id="chatMediaLightbox" class="chat-media-lightbox" style="display: none;">
                    <div class="chat-media-lightbox-backdrop"></div>
                    <button class="chat-media-lightbox-nav chat-media-lightbox-prev" id="chatMediaLightboxPrev" aria-label="Vorheriges Bild">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <div class="chat-media-lightbox-content">
                        <button class="chat-media-lightbox-close" id="chatMediaLightboxClose" aria-label="Schließen">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        <div class="chat-media-lightbox-media">
                            <img id="chatMediaLightboxImage" src="" alt="" style="display: none;">
                            <video id="chatMediaLightboxVideo" src="" controls style="display: none;">
                                Ihr Browser unterstützt das Video-Tag nicht.
                            </video>
                        </div>
                        <div class="chat-media-lightbox-counter" id="chatMediaLightboxCounter" style="display: none;"></div>
                    </div>
                    <button class="chat-media-lightbox-nav chat-media-lightbox-next" id="chatMediaLightboxNext" aria-label="Nächstes Bild">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="chat-input-container" id="chatInputContainer" style="display: none;">
                <div class="chat-file-preview" id="chatFilePreview" style="display: none;"></div>
                <div class="chat-input-wrapper">
                    <input 
                        type="file"
                        class="chat-file-input" 
                        id="chatFileInput" 
                        accept="image/*,video/*"
                        aria-label="Datei hochladen"
                        multiple
                        style="display: none;"
                    >
                    <button class="chat-file-btn" id="chatFileBtn" aria-label="Datei hochladen" type="button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </button>
                    <input 
                        type="text"
                        class="chat-input" 
                        id="chatInput" 
                        placeholder="Nachricht eingeben..."
                    >
                    <button class="chat-send-btn" id="chatSendBtn" aria-label="Senden">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="chat-box-resize-handle" id="chatBoxResizeHandle"></div>
</div>



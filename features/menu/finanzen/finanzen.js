document.addEventListener('DOMContentLoaded', function() {
    const finanzenOpenBtn = document.getElementById('finanzenOpenBtn');
    const mobileFinanzenBtn = document.getElementById('mobileFinanzenBtn');
    const finanzenModalOverlay = document.getElementById('finanzenModalOverlay');
    const finanzenModalContainer = document.getElementById('finanzenModalContainer');
    const finanzenPapersContainer = document.getElementById('finanzenPapersContainer');
    const finanzenModalClose = document.getElementById('finanzenModalClose');
    const finanzenViewToggleBtn = document.getElementById('finanzenViewToggleBtn');
    const papers = document.querySelectorAll('.a4-paper');
    
    let isGridView = false;
    let savedPaperTransforms = {};
    let savedContainerState = {};
    
    // Function to open finanzen modal
    function openFinanzenModal() {
        if (finanzenModalOverlay) {
            finanzenModalOverlay.style.display = 'flex';
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.height = '100%';
            
            // Reset to free view when opening
            if (isGridView) {
                toggleView();
            }
            resetContainerTransform();
            
            // Close mobile menu if open
            const navMenuMobile = document.getElementById('navMenuMobile');
            if (navMenuMobile && navMenuMobile.classList.contains('active')) {
                const mobileMenuToggle = document.getElementById('mobileMenuToggle');
                if (mobileMenuToggle) mobileMenuToggle.classList.remove('active');
                if (navMenuMobile) navMenuMobile.classList.remove('active');
                const navMenuOverlay = document.getElementById('navMenuOverlay');
                if (navMenuOverlay) navMenuOverlay.classList.remove('active');
            }
        }
    }
    
    if (!finanzenModalOverlay || !finanzenPapersContainer) {
        return;
    }
    
    // Desktop button
    if (finanzenOpenBtn) {
        finanzenOpenBtn.addEventListener('click', openFinanzenModal);
    }
    
    // Mobile button
    if (mobileFinanzenBtn) {
        mobileFinanzenBtn.addEventListener('click', openFinanzenModal);
    }
    
    let isDragging = false;
    let draggedPaper = null;
    let startX = 0;
    let startY = 0;
    let paperTransforms = {};
    let containerScale = 1;
    let containerRotation = 0;
    let containerX = 0;
    let containerY = 0;
    let highestZIndex = 100; // Start with a higher base z-index
    
    // Initialize paper transforms
    papers.forEach((paper, index) => {
        const rect = paper.getBoundingClientRect();
        paperTransforms[index] = {
            x: 0,
            y: 0,
            rotation: 0
        };
        // Set initial z-index (higher base value)
        paper.style.zIndex = highestZIndex + index;
    });
    
    // Update highestZIndex to reflect initial state
    highestZIndex = highestZIndex + papers.length;
    
    // Öffne Modal (this is a duplicate, but kept for compatibility)
    // The openFinanzenModal function is already called above
    
    // Function to close finanzen modal
    function closeFinanzenModal() {
        if (finanzenModalOverlay) {
            finanzenModalOverlay.style.display = 'none';
            // Restore background scrolling
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
        }
    }
    
    // Schließe Modal
    finanzenModalClose.addEventListener('click', function(e) {
        e.stopPropagation();
        closeFinanzenModal();
    });
    
    finanzenModalOverlay.addEventListener('click', function(e) {
        if (e.target === finanzenModalOverlay) {
            closeFinanzenModal();
        }
    });
    
    // Toggle between Grid and Free View
    function toggleView() {
        isGridView = !isGridView;
        
        if (isGridView) {
            // Switch to Grid View
            finanzenPapersContainer.classList.add('grid-view');
            if (finanzenViewToggleBtn) {
                finanzenViewToggleBtn.classList.add('grid-mode');
            }
            
            // Save current state
            savedContainerState = {
                scale: containerScale,
                rotation: containerRotation,
                x: containerX,
                y: containerY
            };
            
            papers.forEach((paper, index) => {
                savedPaperTransforms[index] = { ...paperTransforms[index] };
            });
            
            // Reset container transform
            resetContainerTransform();
            
            // Disable dragging in grid view
            papers.forEach((paper) => {
                paper.style.pointerEvents = 'auto';
            });
        } else {
            // Switch to Free View
            finanzenPapersContainer.classList.remove('grid-view');
            if (finanzenViewToggleBtn) {
                finanzenViewToggleBtn.classList.remove('grid-mode');
            }
            
            // Restore saved state
            containerScale = savedContainerState.scale || 1;
            containerRotation = savedContainerState.rotation || 0;
            containerX = savedContainerState.x || 0;
            containerY = savedContainerState.y || 0;
            updateContainerTransform();
            
            // Restore paper transforms
            papers.forEach((paper, index) => {
                if (savedPaperTransforms[index]) {
                    paperTransforms[index] = { ...savedPaperTransforms[index] };
                    updatePaperTransform(paper, index);
                }
            });
            
            // Re-enable dragging
            papers.forEach((paper) => {
                paper.style.pointerEvents = 'auto';
            });
        }
    }
    
    // View Toggle Button
    if (finanzenViewToggleBtn) {
        finanzenViewToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleView();
        });
    }
    
    // Open PDF View for a paper
    let currentPDFModal = null; // Track current PDF modal to prevent duplicates
    
    function openPaperPDFView(paper) {
        // Prevent opening multiple modals
        if (currentPDFModal && document.body.contains(currentPDFModal)) {
            return;
        }
        
        // Create PDF view modal
        const pdfModal = document.createElement('div');
        pdfModal.className = 'pdf-view-modal';
        pdfModal.innerHTML = `
            <div class="pdf-view-overlay"></div>
            <div class="pdf-view-container">
                <button class="pdf-view-close" id="pdfViewClose">×</button>
                <div class="pdf-view-content">
                    ${paper.querySelector('.finanzen-paper-content').innerHTML}
                </div>
            </div>
        `;
        document.body.appendChild(pdfModal);
        currentPDFModal = pdfModal;
        
        // Close handlers
        const closeBtn = pdfModal.querySelector('#pdfViewClose');
        const overlay = pdfModal.querySelector('.pdf-view-overlay');
        
        const closePDF = function(e) {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }
            if (currentPDFModal && document.body.contains(currentPDFModal)) {
                document.body.removeChild(currentPDFModal);
                currentPDFModal = null;
            }
        };
        
        // Multiple event handlers for better compatibility
        closeBtn.addEventListener('click', closePDF);
        closeBtn.addEventListener('touchend', closePDF);
        overlay.addEventListener('click', closePDF);
        overlay.addEventListener('touchend', closePDF);
        
        // Close on ESC key
        const escHandler = function(e) {
            if (e.key === 'Escape') {
                closePDF();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
        
        // Show modal
        setTimeout(() => {
            pdfModal.classList.add('active');
        }, 10);
    }
    
    // Reset Container Transform
    function resetContainerTransform() {
        containerScale = 1;
        containerRotation = 0;
        containerX = 0;
        containerY = 0;
        updateContainerTransform();
    }
    
    // Update Container Transform
    function updateContainerTransform() {
        finanzenPapersContainer.style.transform = `translate(${containerX}px, ${containerY}px) rotate(${containerRotation}deg) scale(${containerScale})`;
    }
    
    // Update Paper Transform
    function updatePaperTransform(paper, index) {
        const transform = paperTransforms[index];
        const baseTransform = getBaseTransform(index);
        paper.style.transform = `translate(${baseTransform.x + transform.x}px, ${baseTransform.y + transform.y}px) rotate(${baseTransform.rotation + transform.rotation}deg)`;
    }
    
    // Get base transform from CSS
    function getBaseTransform(index) {
        const transforms = [
            { x: -320, y: -120, rotation: -4 },
            { x: -80, y: -100, rotation: 3 },
            { x: 160, y: -140, rotation: -2 },
            { x: 400, y: -110, rotation: 5 },
            { x: -280, y: 120, rotation: -3 },
            { x: 0, y: 140, rotation: 2 },
            { x: 280, y: 130, rotation: -4 },
            { x: 560, y: 150, rotation: 3 }
        ];
        return transforms[index] || { x: 0, y: 0, rotation: 0 };
    }
    
    // Container Zoom with mouse wheel (zoom to mouse position)
    finanzenModalOverlay.addEventListener('wheel', function(e) {
        // In grid view, allow normal scrolling
        if (isGridView) {
            return;
        }
        
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) {
            // Strg/Cmd gedrückt = Rotation
            const delta = e.deltaY > 0 ? -5 : 5;
            containerRotation = (containerRotation + delta) % 360;
            updateContainerTransform();
        } else {
            // Normal = Zoom zum Mauspunkt (unabhängig von Blättern)
            const containerRect = finanzenPapersContainer.getBoundingClientRect();
            const containerCenterX = containerRect.left + containerRect.width / 2;
            const containerCenterY = containerRect.top + containerRect.height / 2;
            
            // Mausposition in Viewport-Koordinaten
            const mouseX = e.clientX;
            const mouseY = e.clientY;
            
            // Mausposition relativ zum Container-Zentrum (Viewport-Koordinaten)
            const relX = mouseX - containerCenterX;
            const relY = mouseY - containerCenterY;
            
            // Konvertiere zu Container-Koordinaten (rückgängig Rotation)
            const angle = -containerRotation * Math.PI / 180;
            const cos = Math.cos(angle);
            const sin = Math.sin(angle);
            const unrotatedX = relX * cos - relY * sin;
            const unrotatedY = relX * sin + relY * cos;
            
            // Mausposition in Container-Koordinaten (vor Zoom)
            // Diese Position muss nach dem Zoom gleich bleiben
            const worldX = (unrotatedX / containerScale) - containerX;
            const worldY = (unrotatedY / containerScale) - containerY;
            
            // Berechne neuen Zoom-Level
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            const oldScale = containerScale;
            const newScale = Math.max(0.3, Math.min(2, containerScale + delta));
            
            // Wenn Scale-Limit erreicht, nichts tun
            if (oldScale === newScale) {
                return;
            }
            
            // Berechne neue Container-Position
            // Die Welt-Position (worldX, worldY) muss gleich bleiben
            // Nach dem Zoom: (unrotatedX / newScale) - newContainerX = worldX
            // Also: newContainerX = (unrotatedX / newScale) - worldX
            const newContainerX = (unrotatedX / newScale) - worldX;
            const newContainerY = (unrotatedY / newScale) - worldY;
            
            containerX = newContainerX;
            containerY = newContainerY;
            containerScale = newScale;
            updateContainerTransform();
        }
    }, { passive: false });
    
    // Drag Container (when clicking on overlay)
    let containerDragging = false;
    let containerStartX = 0;
    let containerStartY = 0;
    let containerStartMouseX = 0;
    let containerStartMouseY = 0;
    
    finanzenModalContainer.addEventListener('mousedown', function(e) {
        // In grid view, don't allow container dragging
        if (isGridView) {
            return;
        }
        
        if (e.target === finanzenModalContainer || e.target === finanzenPapersContainer) {
            containerDragging = true;
            containerStartMouseX = e.clientX;
            containerStartMouseY = e.clientY;
            containerStartX = containerX;
            containerStartY = containerY;
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (containerDragging) {
            e.preventDefault();
            // Delta in Screen-Pixeln (konstant unabhängig vom Zoom)
            // Berücksichtige Rotation für die Bewegung
            const screenDeltaX = e.clientX - containerStartMouseX;
            const screenDeltaY = e.clientY - containerStartMouseY;
            
            // Rotiere das Delta zurück (weil Container rotiert ist)
            const angle = -containerRotation * Math.PI / 180;
            const cos = Math.cos(angle);
            const sin = Math.sin(angle);
            const rotatedDeltaX = screenDeltaX * cos - screenDeltaY * sin;
            const rotatedDeltaY = screenDeltaX * sin + screenDeltaY * cos;
            
            // Konvertiere zu World-Koordinaten (durch Scale teilen)
            const worldDeltaX = rotatedDeltaX / containerScale;
            const worldDeltaY = rotatedDeltaY / containerScale;
            
            containerX = containerStartX + worldDeltaX;
            containerY = containerStartY + worldDeltaY;
            updateContainerTransform();
        } else if (isDragging && draggedPaper) {
            e.preventDefault();
            const index = parseInt(draggedPaper.dataset.paperIndex);
            
            // Check if mouse moved significantly (drag detection)
            const screenDeltaX = Math.abs(e.clientX - paperStartScreenX);
            const screenDeltaY = Math.abs(e.clientY - paperStartScreenY);
            if (screenDeltaX > CLICK_THRESHOLD || screenDeltaY > CLICK_THRESHOLD) {
                hasDragged = true;
            }
            
            // Ensure dragged paper always has the highest z-index during dragging
            if (draggedPaper.style.zIndex < highestZIndex) {
                highestZIndex += 1;
                draggedPaper.style.zIndex = highestZIndex;
            }
            
            // Set rotation to 0 during drag
            paperTransforms[index].rotation = -getBaseTransform(index).rotation;
            
            // Berechne Delta zur Start-Position
            const containerRect = finanzenPapersContainer.getBoundingClientRect();
            const containerCenterX = containerRect.left + containerRect.width / 2;
            const containerCenterY = containerRect.top + containerRect.height / 2;
            
            // Aktuelle Mausposition relativ zum Container (in World-Koordinaten)
            const currentMouseX = (e.clientX - containerCenterX) / containerScale;
            const currentMouseY = (e.clientY - containerCenterY) / containerScale;
            
            // Delta zur Start-Position (beide sind in World-Koordinaten)
            const deltaX = currentMouseX - paperStartMouseX;
            const deltaY = currentMouseY - paperStartMouseY;
            
            // Update Paper Position
            paperTransforms[index].x = paperStartTransformX + deltaX;
            paperTransforms[index].y = paperStartTransformY + deltaY;
            updatePaperTransform(draggedPaper, index);
        }
    });
    
    document.addEventListener('mouseup', function(e) {
        containerDragging = false;
        if (isDragging) {
            const wasDragging = draggedPaper;
            const index = wasDragging ? parseInt(wasDragging.dataset.paperIndex) : null;
            
            if (!hasDragged && wasDragging && !isGridView) {
                // Precise click - open PDF view (only in free view, not grid view)
                openPaperPDFView(wasDragging);
            } else if (hasDragged && wasDragging && index !== null) {
                // Restore original rotation after drag
                const baseTransform = getBaseTransform(index);
                paperTransforms[index].rotation = paperOriginalRotation - baseTransform.rotation;
                updatePaperTransform(wasDragging, index);
            }
            
            isDragging = false;
            if (draggedPaper) {
                draggedPaper.style.cursor = 'grab';
            }
            draggedPaper = null;
            hasDragged = false;
        }
    });
    
    // Individual Paper Drag
    let paperStartMouseX = 0;
    let paperStartMouseY = 0;
    let paperStartTransformX = 0;
    let paperStartTransformY = 0;
    let paperStartScreenX = 0;
    let paperStartScreenY = 0;
    let paperOriginalRotation = 0;
    let hasDragged = false;
    const CLICK_THRESHOLD = 5; // pixels movement threshold for click vs drag
    
    papers.forEach((paper) => {
        let paperClickHandled = false; // Prevent double opening
        
        paper.addEventListener('mousedown', function(e) {
            e.stopPropagation();
            
            // In grid view, clicking opens PDF view instead of dragging
            if (isGridView) {
                if (!paperClickHandled) {
                    paperClickHandled = true;
                    openPaperPDFView(paper);
                    // Reset after a short delay
                    setTimeout(() => {
                        paperClickHandled = false;
                    }, 300);
                }
                return;
            }
            
            const index = parseInt(paper.dataset.paperIndex);
            
            // Bring clicked paper to front and ensure it has the highest z-index
            highestZIndex += 1;
            paper.style.zIndex = highestZIndex;
            
            // Store original rotation before dragging
            const baseTransform = getBaseTransform(index);
            paperOriginalRotation = baseTransform.rotation + paperTransforms[index].rotation;
            
            // Track if mouse moved (for click detection)
            hasDragged = false;
            paperStartScreenX = e.clientX;
            paperStartScreenY = e.clientY;
            
            isDragging = true;
            draggedPaper = paper;
            
            // Speichere Start-Positionen
            const containerRect = finanzenPapersContainer.getBoundingClientRect();
            const containerCenterX = containerRect.left + containerRect.width / 2;
            const containerCenterY = containerRect.top + containerRect.height / 2;
            
            // Mausposition relativ zum Container beim Start
            paperStartMouseX = (e.clientX - containerCenterX) / containerScale;
            paperStartMouseY = (e.clientY - containerCenterY) / containerScale;
            
            // Aktuelle Transform-Werte beim Start
            paperStartTransformX = paperTransforms[index].x;
            paperStartTransformY = paperTransforms[index].y;
            
            paper.style.cursor = 'grabbing';
        });
        
        // Set initial cursor
        paper.style.cursor = 'grab';
    });
    
    // Touch Support
    let touchStartDistance = 0;
    let touchStartRotation = 0;
    let initialContainerRotation = 0;
    let touchContainerDragging = false;
    let touchContainerStartX = 0;
    let touchContainerStartY = 0;
    let touchContainerStartScreenX = 0;
    let touchContainerStartScreenY = 0;
    let touchPaperDragging = false;
    let touchPaperStartX = 0;
    let touchPaperStartY = 0;
    let touchPaperStartScreenX = 0;
    let touchPaperStartScreenY = 0;
    let touchPaperStartTransformX = 0;
    let touchPaperStartTransformY = 0;
    let touchPaperOriginalRotation = 0;
    let touchHasDragged = false;
    let touchDraggedPaper = null;
    let touchStartTime = 0;
    
    // Helper function to get touch coordinates
    function getTouchCoords(touch) {
        return { x: touch.clientX, y: touch.clientY };
    }
    
    // Touch start handler
    let touchPaperClickHandled = false; // Prevent double opening in grid view
    
    finanzenModalOverlay.addEventListener('touchstart', function(e) {
        if (isGridView) {
            // In grid view, only allow scrolling, no dragging
            if (e.touches.length === 1) {
                const touch = e.touches[0];
                const target = document.elementFromPoint(touch.clientX, touch.clientY);
                const paper = target.closest('.a4-paper');
                
                if (paper && !touchPaperClickHandled) {
                    // Open PDF view on tap in grid mode
                    touchStartTime = Date.now();
                    touchHasDragged = false;
                    touchPaperStartScreenX = touch.clientX;
                    touchPaperStartScreenY = touch.clientY;
                    touchDraggedPaper = paper;
                    touchPaperClickHandled = true;
                    
                    // Reset after delay
                    setTimeout(() => {
                        touchPaperClickHandled = false;
                    }, 500);
                }
            }
            return;
        }
        
        if (e.touches.length === 2) {
            // Two-finger gesture: Zoom and Rotation
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            touchStartDistance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
            touchStartRotation = Math.atan2(
                touch2.clientY - touch1.clientY,
                touch2.clientX - touch1.clientX
            ) * 180 / Math.PI;
            initialContainerRotation = containerRotation;
            
            // Cancel any single-touch operations
            touchContainerDragging = false;
            touchPaperDragging = false;
        } else if (e.touches.length === 1) {
            // Single touch: Check if on paper or container
            const touch = e.touches[0];
            const target = document.elementFromPoint(touch.clientX, touch.clientY);
            const paper = target.closest('.a4-paper');
            
            touchStartTime = Date.now();
            touchHasDragged = false;
            
            if (paper) {
                // Touch on paper
                e.stopPropagation();
                const index = parseInt(paper.dataset.paperIndex);
                
                // Bring paper to front
                highestZIndex += 1;
                paper.style.zIndex = highestZIndex;
                
                // Store original rotation
                const baseTransform = getBaseTransform(index);
                touchPaperOriginalRotation = baseTransform.rotation + paperTransforms[index].rotation;
                
                touchPaperDragging = true;
                touchDraggedPaper = paper;
                touchPaperStartScreenX = touch.clientX;
                touchPaperStartScreenY = touch.clientY;
                
                // Calculate start positions
                const containerRect = finanzenPapersContainer.getBoundingClientRect();
                const containerCenterX = containerRect.left + containerRect.width / 2;
                const containerCenterY = containerRect.top + containerRect.height / 2;
                
                touchPaperStartX = (touch.clientX - containerCenterX) / containerScale;
                touchPaperStartY = (touch.clientY - containerCenterY) / containerScale;
                touchPaperStartTransformX = paperTransforms[index].x;
                touchPaperStartTransformY = paperTransforms[index].y;
            } else if (e.target === finanzenModalContainer || e.target === finanzenPapersContainer) {
                // Touch on container/overlay
                touchContainerDragging = true;
                touchContainerStartScreenX = touch.clientX;
                touchContainerStartScreenY = touch.clientY;
                touchContainerStartX = containerX;
                touchContainerStartY = containerY;
            }
        }
    }, { passive: true });
    
    // Touch move handler
    finanzenModalOverlay.addEventListener('touchmove', function(e) {
        if (isGridView) {
            // In grid view, allow normal scrolling
            if (e.touches.length === 1 && touchDraggedPaper) {
                const touch = e.touches[0];
                const screenDeltaX = Math.abs(touch.clientX - touchPaperStartScreenX);
                const screenDeltaY = Math.abs(touch.clientY - touchPaperStartScreenY);
                
                // If moved significantly, it's a scroll, not a tap
                if (screenDeltaX > CLICK_THRESHOLD || screenDeltaY > CLICK_THRESHOLD) {
                    touchHasDragged = true;
                }
            }
            return;
        }
        
        if (e.touches.length === 2) {
            // Two-finger gesture: Zoom and Rotation
            e.preventDefault();
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            const currentDistance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
            const currentRotation = Math.atan2(
                touch2.clientY - touch1.clientY,
                touch2.clientX - touch1.clientX
            ) * 180 / Math.PI;
            
            // Zoom
            const scaleChange = currentDistance / touchStartDistance;
            const newScale = Math.max(0.3, Math.min(2, containerScale * scaleChange));
            containerScale = newScale;
            touchStartDistance = currentDistance;
            
            // Rotation
            const rotationChange = currentRotation - touchStartRotation;
            containerRotation = initialContainerRotation + rotationChange;
            
            updateContainerTransform();
        } else if (e.touches.length === 1) {
            // Single touch: Drag paper or container
            e.preventDefault();
            const touch = e.touches[0];
            
            if (touchPaperDragging && touchDraggedPaper) {
                // Drag paper
                const index = parseInt(touchDraggedPaper.dataset.paperIndex);
                
                // Check if moved significantly
                const screenDeltaX = Math.abs(touch.clientX - touchPaperStartScreenX);
                const screenDeltaY = Math.abs(touch.clientY - touchPaperStartScreenY);
                if (screenDeltaX > CLICK_THRESHOLD || screenDeltaY > CLICK_THRESHOLD) {
                    touchHasDragged = true;
                }
                
                // Set rotation to 0 during drag
                paperTransforms[index].rotation = -getBaseTransform(index).rotation;
                
                // Calculate delta
                const containerRect = finanzenPapersContainer.getBoundingClientRect();
                const containerCenterX = containerRect.left + containerRect.width / 2;
                const containerCenterY = containerRect.top + containerRect.height / 2;
                
                const currentTouchX = (touch.clientX - containerCenterX) / containerScale;
                const currentTouchY = (touch.clientY - containerCenterY) / containerScale;
                
                const deltaX = currentTouchX - touchPaperStartX;
                const deltaY = currentTouchY - touchPaperStartY;
                
                paperTransforms[index].x = touchPaperStartTransformX + deltaX;
                paperTransforms[index].y = touchPaperStartTransformY + deltaY;
                updatePaperTransform(touchDraggedPaper, index);
            } else if (touchContainerDragging) {
                // Drag container
                const screenDeltaX = touch.clientX - touchContainerStartScreenX;
                const screenDeltaY = touch.clientY - touchContainerStartScreenY;
                
                // Rotate delta back (because container is rotated)
                const angle = -containerRotation * Math.PI / 180;
                const cos = Math.cos(angle);
                const sin = Math.sin(angle);
                const rotatedDeltaX = screenDeltaX * cos - screenDeltaY * sin;
                const rotatedDeltaY = screenDeltaX * sin + screenDeltaY * cos;
                
                // Convert to world coordinates
                const worldDeltaX = rotatedDeltaX / containerScale;
                const worldDeltaY = rotatedDeltaY / containerScale;
                
                containerX = touchContainerStartX + worldDeltaX;
                containerY = touchContainerStartY + worldDeltaY;
                updateContainerTransform();
            }
        }
    }, { passive: false });
    
    // Touch end handler
    finanzenModalOverlay.addEventListener('touchend', function(e) {
        if (isGridView) {
            // In grid view, open PDF on tap (only if not already handled)
            if (touchDraggedPaper && !touchHasDragged && touchPaperClickHandled) {
                const touchDuration = Date.now() - touchStartTime;
                if (touchDuration < 300) {
                    openPaperPDFView(touchDraggedPaper);
                    touchPaperClickHandled = false; // Reset immediately after opening
                }
            }
            touchDraggedPaper = null;
            touchHasDragged = false;
            return;
        }
        
        if (touchPaperDragging && touchDraggedPaper) {
            const index = parseInt(touchDraggedPaper.dataset.paperIndex);
            const touchDuration = Date.now() - touchStartTime;
            
            if (!touchHasDragged && touchDuration < 300) {
                // Quick tap without drag - open PDF view
                openPaperPDFView(touchDraggedPaper);
            } else if (touchHasDragged) {
                // Restore original rotation after drag
                const baseTransform = getBaseTransform(index);
                paperTransforms[index].rotation = touchPaperOriginalRotation - baseTransform.rotation;
                updatePaperTransform(touchDraggedPaper, index);
            }
            
            touchPaperDragging = false;
            touchDraggedPaper = null;
            touchHasDragged = false;
        }
        
        touchContainerDragging = false;
        
        // Reset two-finger gesture if no touches left
        if (e.touches.length === 0) {
            touchStartDistance = 0;
            touchStartRotation = 0;
        }
    }, { passive: true });
    
    // Touch cancel handler
    finanzenModalOverlay.addEventListener('touchcancel', function(e) {
        touchPaperDragging = false;
        touchContainerDragging = false;
        touchDraggedPaper = null;
        touchHasDragged = false;
        touchStartDistance = 0;
        touchStartRotation = 0;
    }, { passive: true });
});


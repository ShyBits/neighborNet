document.addEventListener('DOMContentLoaded', function() {
    const finanzenOpenBtn = document.getElementById('finanzenOpenBtn');
    const finanzenModalOverlay = document.getElementById('finanzenModalOverlay');
    const finanzenModalContainer = document.getElementById('finanzenModalContainer');
    const finanzenPapersContainer = document.getElementById('finanzenPapersContainer');
    const finanzenModalClose = document.getElementById('finanzenModalClose');
    const papers = document.querySelectorAll('.a4-paper');
    
    if (!finanzenOpenBtn || !finanzenModalOverlay || !finanzenPapersContainer) {
        return;
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
    
    // Öffne Modal
    finanzenOpenBtn.addEventListener('click', function() {
        finanzenModalOverlay.style.display = 'flex';
        resetContainerTransform();
    });
    
    // Schließe Modal
    finanzenModalClose.addEventListener('click', function(e) {
        e.stopPropagation();
        finanzenModalOverlay.style.display = 'none';
    });
    
    finanzenModalOverlay.addEventListener('click', function(e) {
        if (e.target === finanzenModalOverlay) {
            finanzenModalOverlay.style.display = 'none';
        }
    });
    
    // Open PDF View for a paper
    function openPaperPDFView(paper) {
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
        
        // Close handlers
        const closeBtn = pdfModal.querySelector('#pdfViewClose');
        const overlay = pdfModal.querySelector('.pdf-view-overlay');
        
        const closePDF = function() {
            document.body.removeChild(pdfModal);
        };
        
        closeBtn.addEventListener('click', closePDF);
        overlay.addEventListener('click', closePDF);
        
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
            
            if (!hasDragged && wasDragging) {
                // Precise click - open PDF view
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
        paper.addEventListener('mousedown', function(e) {
            e.stopPropagation();
            
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
    
    finanzenModalOverlay.addEventListener('touchstart', function(e) {
        if (e.touches.length === 2) {
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
        }
    });
    
    finanzenModalOverlay.addEventListener('touchmove', function(e) {
        e.preventDefault();
        if (e.touches.length === 2) {
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
            containerScale = Math.max(0.3, Math.min(2, containerScale * scaleChange));
            touchStartDistance = currentDistance;
            
            // Rotation
            const rotationChange = currentRotation - touchStartRotation;
            containerRotation = initialContainerRotation + rotationChange;
            
            updateContainerTransform();
        }
    });
});


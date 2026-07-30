/**
 * Interactive Journal JS Engine
 * Adheres strictly to the Slow Computer Protocol (multi-tier delayed init).
 */
(function () {
    'use strict';

    let initialized = false;

    function initNotedApp() {
        if (initialized) return;

        const appContainer = document.querySelector('.noted-app-container');
        if (!appContainer && !document.querySelector('[data-noted-journal-page]')) {
            return;
        }

        initialized = true;
        console.log('[Noted.edu] Initializing interactive journal engine...');

        // Core State
        const state = {
            paperStyle: 'lined',
            isDrawingMode: false,
            drawingTool: 'pen',
            drawingColor: '#236a54',
            drawingLineWidth: 3,
            drawingHistory: [],
            activeMediaItem: null,
            viewMode: 'editor', // 'editor' or 'calendar'
            isRecording: false,
            mediaRecorder: null,
            audioChunks: [],
            recordTimer: null,
            recordSeconds: 0,
            subjectFilter: 'all',
            searchQuery: '',
            calendarDate: new Date(2026, 6, 1) // July 2026 default
        };

        // DOM Element Cache
        const elements = {
            viewBtns: document.querySelectorAll('.noted-view-btn'),
            editorView: document.getElementById('notedEditorView'),
            calendarView: document.getElementById('notedCalendarView'),
            paperContainer: document.getElementById('notedPaperContainer'),
            paperContent: document.getElementById('notedPaperContent'),
            drawingCanvas: document.getElementById('drawingCanvas'),
            paperStyleSelect: document.getElementById('paperStyleSelect'),
            toggleDrawingBtn: document.getElementById('toggleDrawingBtn'),
            drawingToolbar: document.getElementById('drawingToolbar'),
            penBtn: document.getElementById('penToolBtn'),
            highlighterBtn: document.getElementById('highlighterToolBtn'),
            eraserBtn: document.getElementById('eraserToolBtn'),
            colorDots: document.querySelectorAll('.color-dot'),
            strokeWidthSlider: document.getElementById('strokeWidthSlider'),
            undoBtn: document.getElementById('undoDrawingBtn'),
            clearBtn: document.getElementById('clearDrawingBtn'),
            addStickyBtn: document.getElementById('addStickyBtn'),
            addImageBtn: document.getElementById('addImageBtn'),
            geminiAiBtn: document.getElementById('geminiAiBtn'),
            pdfExportBtn: document.getElementById('pdfExportBtn'),
            stampBtns: document.querySelectorAll('.stamp-btn'),
            imageFileInput: document.getElementById('imageFileInput'),
            
            stampBtns: document.querySelectorAll('.stamp-btn'),
            imageFileInput: document.getElementById('imageFileInput'),

            // AI Modal
            aiModal: document.getElementById('geminiAiModal'),
            closeAiModalBtn: document.getElementById('closeAiModalBtn'),
            aiTabBtns: document.querySelectorAll('.ai-tab-btn'),
            aiPromptText: document.getElementById('aiPromptText'),
            aiGenerateBtn: document.getElementById('aiGenerateBtn'),
            aiResultBox: document.getElementById('aiResultBox'),
            aiInsertBtn: document.getElementById('aiInsertBtn'),
            aiCopyBtn: document.getElementById('aiCopyBtn'),

            // Subject Modal & Controls
            newSubjectBtn: document.getElementById('newSubjectBtn'),
            newSubjectModal: document.getElementById('newSubjectModal'),
            saveSubjectBtn: document.getElementById('saveSubjectBtn'),
            closeSubjectModalBtn: document.getElementById('closeSubjectModalBtn'),

            // Canvas Form Save Payload & Data
            canvasJsonInput: document.getElementById('canvasJsonInput'),
            journalForm: document.querySelector('.journal-compose-form')
        };

        // 1. View Switcher (Editor vs Calendar)
        if (elements.viewBtns) {
            elements.viewBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const mode = btn.dataset.view;
                    state.viewMode = mode;

                    elements.viewBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    if (elements.editorView && elements.calendarView) {
                        if (mode === 'calendar') {
                            elements.editorView.style.display = 'none';
                            elements.calendarView.style.display = 'block';
                            renderInteractiveCalendar();
                        } else {
                            elements.editorView.style.display = 'grid';
                            elements.calendarView.style.display = 'none';
                        }
                    }
                });
            });
        }

        // 2. Paper Style Switcher
        if (elements.paperStyleSelect && elements.paperContainer) {
            elements.paperStyleSelect.addEventListener('change', (e) => {
                const style = e.target.value;
                state.paperStyle = style;
                elements.paperContainer.className = 'noted-paper-container paper-' + style;
            });
        }

        // 3. HTML5 Drawing Canvas Setup
        let ctx = null;
        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;

        function resizeCanvas() {
            if (!elements.drawingCanvas || !elements.paperContainer) return;
            elements.drawingCanvas.width = elements.paperContainer.clientWidth;
            elements.drawingCanvas.height = Math.max(elements.paperContainer.clientHeight, 700);
            redrawCanvasHistory();
        }

        if (elements.drawingCanvas) {
            ctx = elements.drawingCanvas.getContext('2d');
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
        }

        // Drawing Mode Toggle
        if (elements.toggleDrawingBtn) {
            elements.toggleDrawingBtn.addEventListener('click', () => {
                state.isDrawingMode = !state.isDrawingMode;
                elements.toggleDrawingBtn.classList.toggle('active', state.isDrawingMode);
                if (elements.drawingCanvas) {
                    elements.drawingCanvas.classList.toggle('active', state.isDrawingMode);
                }
                if (elements.drawingToolbar) {
                    elements.drawingToolbar.style.display = state.isDrawingMode ? 'flex' : 'none';
                }
            });
        }

        // Drawing Tool Selection
        function selectTool(toolName, btnElement) {
            state.drawingTool = toolName;
            [elements.penBtn, elements.highlighterBtn, elements.eraserBtn].forEach(b => {
                if (b) b.classList.remove('selected');
            });
            if (btnElement) btnElement.classList.add('selected');
        }

        if (elements.penBtn) elements.penBtn.addEventListener('click', () => selectTool('pen', elements.penBtn));
        if (elements.highlighterBtn) elements.highlighterBtn.addEventListener('click', () => selectTool('highlighter', elements.highlighterBtn));
        if (elements.eraserBtn) elements.eraserBtn.addEventListener('click', () => selectTool('eraser', elements.eraserBtn));

        // Color Dots
        if (elements.colorDots) {
            elements.colorDots.forEach(dot => {
                dot.addEventListener('click', () => {
                    elements.colorDots.forEach(d => d.classList.remove('active'));
                    dot.classList.add('active');
                    state.drawingColor = dot.dataset.color;
                    if (state.drawingTool === 'eraser') selectTool('pen', elements.penBtn);
                });
            });
        }

        // Stroke Width Slider
        if (elements.strokeWidthSlider) {
            elements.strokeWidthSlider.addEventListener('input', (e) => {
                state.drawingLineWidth = parseInt(e.target.value, 10);
            });
        }

        // Canvas Drawing Events
        if (elements.drawingCanvas && ctx) {
            function getCanvasCoords(e) {
                const rect = elements.drawingCanvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            let currentPath = null;

            function startStroke(e) {
                if (!state.isDrawingMode) return;
                isDrawing = true;
                const coords = getCanvasCoords(e);
                lastX = coords.x;
                lastY = coords.y;

                currentPath = {
                    tool: state.drawingTool,
                    color: state.drawingColor,
                    width: state.drawingLineWidth,
                    points: [{ x: lastX, y: lastY }]
                };
            }

            function drawStroke(e) {
                if (!isDrawing || !state.isDrawingMode) return;
                const coords = getCanvasCoords(e);
                
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(coords.x, coords.y);
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                if (state.drawingTool === 'eraser') {
                    ctx.globalCompositeOperation = 'destination-out';
                    ctx.lineWidth = state.drawingLineWidth * 4;
                } else if (state.drawingTool === 'highlighter') {
                    ctx.globalCompositeOperation = 'multiply';
                    ctx.strokeStyle = state.drawingColor + '66';
                    ctx.lineWidth = state.drawingLineWidth * 3;
                } else {
                    ctx.globalCompositeOperation = 'source-over';
                    ctx.strokeStyle = state.drawingColor;
                    ctx.lineWidth = state.drawingLineWidth;
                }

                ctx.stroke();
                lastX = coords.x;
                lastY = coords.y;

                if (currentPath) {
                    currentPath.points.push({ x: lastX, y: lastY });
                }
            }

            function endStroke() {
                if (!isDrawing) return;
                isDrawing = false;
                ctx.globalCompositeOperation = 'source-over';
                if (currentPath && currentPath.points.length > 0) {
                    state.drawingHistory.push(currentPath);
                }
                currentPath = null;
            }

            elements.drawingCanvas.addEventListener('mousedown', startStroke);
            elements.drawingCanvas.addEventListener('mousemove', drawStroke);
            elements.drawingCanvas.addEventListener('mouseup', endStroke);
            elements.drawingCanvas.addEventListener('mouseleave', endStroke);

            elements.drawingCanvas.addEventListener('touchstart', startStroke);
            elements.drawingCanvas.addEventListener('touchmove', drawStroke);
            elements.drawingCanvas.addEventListener('touchend', endStroke);
        }

        function redrawCanvasHistory() {
            if (!ctx || !elements.drawingCanvas) return;
            ctx.clearRect(0, 0, elements.drawingCanvas.width, elements.drawingCanvas.height);
            
            state.drawingHistory.forEach(path => {
                if (!path.points || path.points.length < 2) return;
                ctx.beginPath();
                ctx.moveTo(path.points[0].x, path.points[0].y);
                for (let i = 1; i < path.points.length; i++) {
                    ctx.lineTo(path.points[i].x, path.points[i].y);
                }
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                if (path.tool === 'eraser') {
                    ctx.globalCompositeOperation = 'destination-out';
                    ctx.lineWidth = path.width * 4;
                } else if (path.tool === 'highlighter') {
                    ctx.globalCompositeOperation = 'multiply';
                    ctx.strokeStyle = path.color + '66';
                    ctx.lineWidth = path.width * 3;
                } else {
                    ctx.globalCompositeOperation = 'source-over';
                    ctx.strokeStyle = path.color;
                    ctx.lineWidth = path.width;
                }
                ctx.stroke();
            });
            ctx.globalCompositeOperation = 'source-over';
        }

        if (elements.undoBtn) {
            elements.undoBtn.addEventListener('click', () => {
                state.drawingHistory.pop();
                redrawCanvasHistory();
            });
        }

        if (elements.clearBtn) {
            elements.clearBtn.addEventListener('click', () => {
                state.drawingHistory = [];
                if (ctx) ctx.clearRect(0, 0, elements.drawingCanvas.width, elements.drawingCanvas.height);
            });
        }

        // 4. Floating Sticky Notes System (Contenteditable = False container to isolate focus)
        function createStickyNote(text = '', color = 'sticky-yellow', x = 60, y = 80) {
            if (!elements.paperContainer) return;
            
            const isReadOnly = !elements.journalForm;
            const sticky = document.createElement('div');
            sticky.className = 'sticky-note-item ' + (color || 'sticky-yellow');
            sticky.setAttribute('contenteditable', 'false');
            sticky.style.left = (typeof x === 'number' ? x + 'px' : x);
            sticky.style.top = (typeof y === 'number' ? y + 'px' : y);

            sticky.innerHTML = `
                <div class="sticky-header">
                    <span><i class="bi bi-grip-vertical"></i> STICKY NOTE</span>
                    <div style="${isReadOnly ? 'display:none;' : ''}">
                        <i class="bi bi-palette sticky-color-trigger" style="cursor:pointer; margin-right:4px;" title="Change color"></i>
                        <i class="bi bi-x-lg sticky-delete-btn" style="cursor:pointer;" title="Delete"></i>
                    </div>
                </div>
                <textarea class="sticky-content" placeholder="Write your sticky note..." ${isReadOnly ? 'disabled' : ''} style="${isReadOnly ? 'cursor: default; pointer-events: none; color: #222;' : ''}">${text}</textarea>
            `;

            elements.paperContainer.appendChild(sticky);
            makeElementDraggable(sticky, sticky.querySelector('.sticky-header'));

            // Isolate Textarea Focus & Prevent Paper Contenteditable Hijacking
            const textarea = sticky.querySelector('.sticky-content');
            ['mousedown', 'touchstart', 'click', 'keydown', 'keyup'].forEach(evtName => {
                textarea.addEventListener(evtName, (e) => {
                    e.stopPropagation();
                });
            });

            sticky.querySelector('.sticky-delete-btn').addEventListener('click', () => sticky.remove());

            const colors = ['sticky-yellow', 'sticky-pink', 'sticky-blue', 'sticky-green', 'sticky-purple'];
            sticky.querySelector('.sticky-color-trigger').addEventListener('click', () => {
                let currentIdx = colors.findIndex(c => sticky.classList.contains(c));
                let nextColor = colors[(currentIdx + 1) % colors.length];
                colors.forEach(c => sticky.classList.remove(c));
                sticky.classList.add(nextColor);
            });
        }

        if (elements.addStickyBtn) {
            elements.addStickyBtn.addEventListener('click', () => createStickyNote());
        }

        // 5. Draggable & Rotatable Media & Image Elements
        function createInteractiveMedia(src, width = 200, height = 150, x = 100, y = 100, rotation = 0) {
            if (!elements.paperContainer) return;

            const isReadOnly = !elements.journalForm;
            const container = document.createElement('div');
            container.className = 'interactive-media-item';
            container.setAttribute('contenteditable', 'false');
            container.style.left = (typeof x === 'number' ? x + 'px' : x);
            container.style.top = (typeof y === 'number' ? y + 'px' : y);
            container.style.width = (typeof width === 'number' ? width + 'px' : width);
            container.style.height = (typeof height === 'number' ? height + 'px' : height);
            container.style.transform = typeof rotation === 'number' ? `rotate(${rotation}deg)` : rotation;

            container.innerHTML = `
                <div class="rotate-handle" title="Drag to rotate" style="${isReadOnly ? 'display:none;' : ''}"><i class="bi bi-arrow-repeat"></i></div>
                <div class="media-quick-bar" style="${isReadOnly ? 'display:none;' : ''}">
                    <button type="button" class="quick-bar-btn media-rot-btn" title="Rotate 45°"><i class="bi bi-arrow-clockwise"></i></button>
                    <button type="button" class="quick-bar-btn media-front-btn" title="Bring to Front"><i class="bi bi-layers"></i></button>
                    <button type="button" class="quick-bar-btn media-del-btn" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
                <img src="${src}" style="width:100%; height:100%; object-fit:cover; border-radius:8px; pointer-events:none;" alt="Sticker/Media">
            `;

            elements.paperContainer.appendChild(container);
            makeElementDraggable(container, container);

            container.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.interactive-media-item').forEach(el => el.classList.remove('selected'));
                container.classList.add('selected');
                state.activeMediaItem = container;
            });

            let currentRot = 0;
            container.querySelector('.media-rot-btn').addEventListener('click', () => {
                currentRot = (currentRot + 45) % 360;
                container.style.transform = `rotate(${currentRot}deg)`;
            });

            container.querySelector('.media-front-btn').addEventListener('click', () => {
                container.style.zIndex = parseInt(container.style.zIndex || 15, 10) + 5;
            });

            container.querySelector('.media-del-btn').addEventListener('click', () => container.remove());
        }

        document.addEventListener('click', () => {
            document.querySelectorAll('.interactive-media-item').forEach(el => el.classList.remove('selected'));
            state.activeMediaItem = null;
        });

        // Stamp Stickers Bar Insertion
        if (elements.stampBtns) {
            elements.stampBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const emoji = btn.dataset.stamp;
                    insertHtmlAtCursor(` <span style="font-size: 20px;">${emoji}</span> `);
                });
            });
        }

        // Image Attachment Trigger
        if (elements.addImageBtn && elements.imageFileInput) {
            elements.addImageBtn.addEventListener('click', () => elements.imageFileInput.click());
            elements.imageFileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (evt) => {
                        createInteractiveMedia(evt.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        function insertHtmlAtCursor(html) {
            if (!elements.paperContent) return;
            elements.paperContent.focus();
            document.execCommand('insertHTML', false, html);
        }

        function makeElementDraggable(element, handle) {
            if (!elements.journalForm) return; // Disable dragging in read-only view

            let posX = 0, posY = 0, initialX = 0, initialY = 0;
            handle.onmousedown = dragMouseDown;
            handle.ontouchstart = dragMouseDown;

            function dragMouseDown(e) {
                if (e.target.classList.contains('sticky-content') || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON' || e.target.classList.contains('bi-x-lg')) {
                    return;
                }
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                initialX = clientX;
                initialY = clientY;

                document.onmouseup = closeDragElement;
                document.onmousemove = elementDrag;
                document.ontouchend = closeDragElement;
                document.ontouchmove = elementDrag;
            }

            function elementDrag(e) {
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                posX = initialX - clientX;
                posY = initialY - clientY;
                initialX = clientX;
                initialY = clientY;

                element.style.top = (element.offsetTop - posY) + "px";
                element.style.left = (element.offsetLeft - posX) + "px";
            }

            function closeDragElement() {
                document.onmouseup = null;
                document.onmousemove = null;
                document.ontouchend = null;
                document.ontouchmove = null;
            }
        }



        // 7. Gemini AI Journal Coach Modal
        if (elements.geminiAiBtn && elements.aiModal) {
            elements.geminiAiBtn.addEventListener('click', () => {
                elements.aiModal.classList.add('open');
            });
        }

        if (elements.closeAiModalBtn && elements.aiModal) {
            elements.closeAiModalBtn.addEventListener('click', () => {
                elements.aiModal.classList.remove('open');
            });
        }

        let currentAiTab = 'reflection';
        if (elements.aiTabBtns) {
            elements.aiTabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    elements.aiTabBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentAiTab = btn.dataset.tab;
                });
            });
        }

        if (elements.aiGenerateBtn) {
            elements.aiGenerateBtn.addEventListener('click', () => {
                const textContent = elements.paperContent ? elements.paperContent.innerText.trim() : '';
                elements.aiResultBox.textContent = '✨ Gemini Coach is thinking...';

                setTimeout(() => {
                    let resultText = '';
                    if (currentAiTab === 'reflection') {
                        resultText = "💡 **Reflection Prompts for Today**:\n"
                            + "1. What was the single most rewarding study breakthrough you had today?\n"
                            + "2. If you could re-attempt one challenge from class, what would you do differently?\n"
                            + "3. How did your current mood affect your focus and comprehension?";
                    } else if (currentAiTab === 'summary') {
                        resultText = "📌 **Key Concepts Summary**:\n"
                            + "• " + (textContent ? textContent.slice(0, 100) + '...' : 'Structured main study points into bullet format.') + "\n"
                            + "• Identified core takeaway and actionable next steps.\n"
                            + "• Formulated targeted review question for revision.";
                    } else if (currentAiTab === 'polish') {
                        resultText = "✍️ **Refined Writing & Academic Tone**:\n"
                            + (textContent || "Today's review provided clear insights into fundamental principles. Concepts were systematically analyzed and documented for upcoming assessments.");
                    } else if (currentAiTab === 'tagging') {
                        resultText = "🏷️ **Suggested Tags**:\n#StudyReflection #ExamPrep #Focus #NotedStudent";
                    }
                    elements.aiResultBox.textContent = resultText;
                }, 800);
            });
        }

        if (elements.aiInsertBtn) {
            elements.aiInsertBtn.addEventListener('click', () => {
                if (elements.aiResultBox.textContent) {
                    insertHtmlAtCursor(`<div style="background:var(--nj-surface-soft); padding:10px; border-left:3px solid var(--nj-primary); margin:10px 0; font-family:var(--nj-font-ui); font-size:14px;">${elements.aiResultBox.textContent.replace(/\n/g, '<br>')}</div>`);
                    elements.aiModal.classList.remove('open');
                }
            });
        }

        if (elements.aiCopyBtn) {
            elements.aiCopyBtn.addEventListener('click', () => {
                if (elements.aiResultBox.textContent) {
                    navigator.clipboard.writeText(elements.aiResultBox.textContent);
                    alert('Copied AI response to clipboard!');
                }
            });
        }

        // 8. Subject Tag Creation Modal
        if (elements.newSubjectBtn && elements.newSubjectModal) {
            elements.newSubjectBtn.addEventListener('click', () => {
                elements.newSubjectModal.classList.add('open');
            });
        }
        if (elements.closeSubjectModalBtn && elements.newSubjectModal) {
            elements.closeSubjectModalBtn.addEventListener('click', () => {
                elements.newSubjectModal.classList.remove('open');
            });
        }
        if (elements.saveSubjectBtn) {
            elements.saveSubjectBtn.addEventListener('click', () => {
                const subName = document.getElementById('newSubjectInput').value.trim();
                if (subName) {
                    const tagList = document.querySelector('.subject-tag-list');
                    if (tagList) {
                        const pill = document.createElement('span');
                        pill.className = 'subject-tag-pill subject-general';
                        pill.textContent = subName;
                        tagList.appendChild(pill);
                    }
                    elements.newSubjectModal.classList.remove('open');
                }
            });
        }

        // 9. PDF Export / Print Trigger
        if (elements.pdfExportBtn) {
            elements.pdfExportBtn.addEventListener('click', () => {
                window.print();
            });
        }

        // 10. Hydrating Saved Canvas Elements (Sticky notes, Pictures, Drawings) on Load
        function hydrateSavedCanvasData() {
            if (!elements.canvasJsonInput || !elements.canvasJsonInput.value) return;
            try {
                const saved = JSON.parse(elements.canvasJsonInput.value);
                if (saved.paperStyle) {
                    state.paperStyle = saved.paperStyle;
                    if (elements.paperContainer) elements.paperContainer.className = 'noted-paper-container paper-' + saved.paperStyle;
                    if (elements.paperStyleSelect) elements.paperStyleSelect.value = saved.paperStyle;
                }
                if (saved.drawingHistory && Array.isArray(saved.drawingHistory)) {
                    state.drawingHistory = saved.drawingHistory;
                    redrawCanvasHistory();
                }
                if (saved.stickyNotes && Array.isArray(saved.stickyNotes)) {
                    saved.stickyNotes.forEach(note => {
                        createStickyNote(note.text, note.colorClass, note.left, note.top);
                    });
                }
                if (saved.mediaItems && Array.isArray(saved.mediaItems)) {
                    saved.mediaItems.forEach(item => {
                        createInteractiveMedia(item.src, item.width, item.height, item.left, item.top, item.transform);
                    });
                }
            } catch (err) {
                console.error('[Noted.edu] Failed to parse saved canvas JSON:', err);
            }
        }
        hydrateSavedCanvasData();

        // 11. Serializing Canvas Elements before Form Submit / Autosave
        window.serializeNotedCanvas = function() {
            if (elements.paperContent && document.getElementById('content')) {
                document.getElementById('content').value = elements.paperContent.innerHTML;
            }

            const stickyNotes = [];
            document.querySelectorAll('.sticky-note-item').forEach(note => {
                stickyNotes.push({
                    text: note.querySelector('.sticky-content').value,
                    left: note.style.left,
                    top: note.style.top,
                    colorClass: Array.from(note.classList).find(c => c.startsWith('sticky-') && c !== 'sticky-note-item')
                });
            });

            const mediaItems = [];
            document.querySelectorAll('.interactive-media-item').forEach(item => {
                mediaItems.push({
                    src: item.querySelector('img').src,
                    left: item.style.left,
                    top: item.style.top,
                    width: item.style.width,
                    height: item.style.height,
                    transform: item.style.transform
                });
            });

            const canvasData = {
                drawingHistory: state.drawingHistory,
                stickyNotes: stickyNotes,
                mediaItems: mediaItems,
                paperStyle: state.paperStyle
            };

            if (elements.canvasJsonInput) {
                elements.canvasJsonInput.value = JSON.stringify(canvasData);
            }
        };

        if (elements.journalForm) {
            elements.journalForm.addEventListener('submit', () => {
                if (typeof window.serializeNotedCanvas === 'function') {
                    window.serializeNotedCanvas();
                }
            });
        }

        // 12. Full Interactive Dynamic Calendar Engine
        function renderInteractiveCalendar() {
            if (!elements.calendarView) return;

            const year = state.calendarDate.getFullYear();
            const month = state.calendarDate.getMonth();

            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            const firstDayOfMonth = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            let rawEntries = [];
            const rawAttr = elements.calendarView.dataset.journalEntries;
            if (rawAttr) {
                try {
                    rawEntries = JSON.parse(rawAttr);
                } catch (e) {
                    console.error('Failed to parse journal entries for calendar', e);
                }
            }

            const entriesByDate = {};
            rawEntries.forEach(e => {
                const dateKey = e.entry_date;
                if (!entriesByDate[dateKey]) entriesByDate[dateKey] = [];
                entriesByDate[dateKey].push(e);
            });

            const subjectColors = {
                'Mathematics': '#1565c0',
                'Biology': '#2e7d32',
                'History': '#e65100',
                'Literature': '#7b1fa2',
                'CS': '#00838f',
                'General': '#236a54'
            };

            const headerHtml = `
                <div class="calendar-header">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="padding:10px; border-radius:12px; background:rgba(35,106,84,0.1); color:#236a54;">
                            <i class="bi bi-calendar3" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h2 style="margin:0; font-size:20px; font-weight:800; color:#17231b;">${monthNames[month]} ${year}</h2>
                            <p style="margin:0; font-size:12px; color:#647064; font-weight:600;">Student Journal & Study Timeline</p>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" id="calPrevBtn" class="button" style="border-radius:12px;"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" id="calTodayBtn" class="button" style="border-radius:12px; font-weight:700; font-size:12px;">Today</button>
                        <button type="button" id="calNextBtn" class="button" style="border-radius:12px;"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            `;

            let dayHeadsHtml = `
                <div class="calendar-grid">
                    <div class="calendar-day-head">Sun</div>
                    <div class="calendar-day-head">Mon</div>
                    <div class="calendar-day-head">Tue</div>
                    <div class="calendar-day-head">Wed</div>
                    <div class="calendar-day-head">Thu</div>
                    <div class="calendar-day-head">Fri</div>
                    <div class="calendar-day-head">Sat</div>
            `;

            for (let i = 0; i < firstDayOfMonth; i++) {
                dayHeadsHtml += `<div class="calendar-day-cell" style="background:rgba(219,228,215,0.2); border-style:dashed;"></div>`;
            }

            const todayStr = new Date().toISOString().slice(0, 10);
            const baseUrl = window.BASE_URL || '';

            for (let dayNum = 1; dayNum <= daysInMonth; dayNum++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
                const isToday = (dateStr === todayStr);
                const dayEntries = entriesByDate[dateStr] || [];

                let notePillsHtml = '';
                dayEntries.forEach(entry => {
                    const color = subjectColors[entry.subject] || '#236a54';
                    const isFav = entry.starred == 1 ? '<i class="bi bi-star-fill" style="color:#ffd700; margin-left:4px;"></i>' : '';
                    notePillsHtml += `
                        <a class="calendar-note-pill" href="${baseUrl}/modules/journal/view.php?id=${entry.journal_id}" style="background:${color}; color:#fff; font-weight:700; margin-top:3px;" title="${entry.title}">
                            <span>${entry.title}</span>${isFav}
                        </a>
                    `;
                });

                dayHeadsHtml += `
                    <div class="calendar-day-cell ${isToday ? 'calendar-today' : ''}">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="calendar-date-num">${dayNum}</span>
                            <button type="button" class="cal-add-entry-btn" data-date="${dateStr}" style="background:transparent; border:none; color:var(--nj-primary); font-size:15px; cursor:pointer; padding:2px;" title="Create note for ${dateStr}"><i class="bi bi-plus-circle-fill"></i></button>
                        </div>
                        <div style="overflow-y:auto; flex:1; max-height:80px;">
                            ${notePillsHtml}
                        </div>
                    </div>
                `;
            }

            dayHeadsHtml += `</div>`;

            elements.calendarView.innerHTML = headerHtml + dayHeadsHtml;

            // Bind Calendar Prev / Next / Today
            document.getElementById('calPrevBtn').addEventListener('click', () => {
                state.calendarDate = new Date(year, month - 1, 1);
                renderInteractiveCalendar();
            });

            document.getElementById('calNextBtn').addEventListener('click', () => {
                state.calendarDate = new Date(year, month + 1, 1);
                renderInteractiveCalendar();
            });

            document.getElementById('calTodayBtn').addEventListener('click', () => {
                state.calendarDate = new Date();
                renderInteractiveCalendar();
            });

            // Bind "+" Add Entry button on date cells
            document.querySelectorAll('.cal-add-entry-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const targetDate = btn.dataset.date;
                    const dateInput = document.querySelector('[data-journal-date]');
                    if (dateInput && elements.editorView) {
                        dateInput.value = targetDate;
                        elements.viewBtns.forEach(b => {
                            if (b.dataset.view === 'editor') b.click();
                        });
                    } else {
                        window.location.href = baseUrl + '/modules/journal/create.php?entry_date=' + targetDate;
                    }
                });
            });
        }

        renderInteractiveCalendar();
    }

    // SLOW COMPUTER PROTOCOL: Multi-stage resilient initialization
    document.addEventListener('DOMContentLoaded', initNotedApp);
    window.addEventListener('load', initNotedApp);
    setTimeout(initNotedApp, 1000);
    setTimeout(initNotedApp, 3000);
    setTimeout(initNotedApp, 5000);

})();

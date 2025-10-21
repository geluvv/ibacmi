// Excel Export Functionality
document.getElementById('exportExcel')?.addEventListener('click', function() {
    const table = document.querySelector('.student-table');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    
    const range = XLSX.utils.decode_range(ws['!ref']);
    
    for (let C = range.s.c; C <= range.e.c; ++C) {
        const cell_address = XLSX.utils.encode_cell({r: 0, c: C});
        if (!ws[cell_address]) continue;
        
        ws[cell_address].s = {
            fill: { fgColor: { rgb: "800000" } },
            font: { color: { rgb: "FFFFFF" }, bold: true },
            alignment: { horizontal: "left", vertical: "center" }
        };
    }
    
    for (let R = range.s.r + 1; R <= range.e.r; ++R) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const cell_address = XLSX.utils.encode_cell({r: R, c: C});
            if (!ws[cell_address]) continue;
            
            ws[cell_address].s = {
                fill: { fgColor: { rgb: R % 2 ? "FFFFFF" : "F9F9F9" } },
                font: { color: { rgb: "000000" } },
                alignment: { horizontal: "left", vertical: "center" },
                border: {
                    top: { style: "thin", color: { rgb: "EEEEEE" } },
                    bottom: { style: "thin", color: { rgb: "EEEEEE" } }
                }
            };
        }
    }
    
    const colWidths = [
        { wch: 15 }, { wch: 25 }, { wch: 20 }, { wch: 12 },
        { wch: 15 }, { wch: 40 }, { wch: 15 }, { wch: 15 }
    ];
    ws['!cols'] = colWidths;
    
    XLSX.utils.book_append_sheet(wb, ws, "Lacking Documents");
    
    const currentDate = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `IBACMI_Lacking_Documents_${currentDate}.xlsx`);
});

// Function to open the update modal (moved to top to ensure it loads first)
function openUpdateModal(studentType, recordId, studentId, missingDocsJson) {
    const missingDocs = JSON.parse(missingDocsJson);
    
    document.getElementById('modalStudentId').textContent = studentId;
    document.getElementById('studentType').value = studentType;
    document.getElementById('recordId').value = recordId;
    document.getElementById('studentIdInput').value = studentId;
    
    const documentsList = document.getElementById('documentsList');
    documentsList.innerHTML = '';
    
    if (missingDocs.length === 0) {
        documentsList.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                All required documents have been submitted.
            </div>
        `;
        return;
    }
    
    // Add document items
    missingDocs.forEach(doc => {
        const docCode = doc.doc_code;
        const docName = doc.doc_name;
        const fileInputName = 'document_' + docCode;
        
        const docItem = document.createElement('div');
        docItem.className = 'document-item';
        docItem.dataset.docType = docCode;
        
        docItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <label class="form-label fw-bold mb-0">${docName}</label>
                    <span class="validation-indicator-inline ms-2" style="display: none;">
                        <i class="fas fa-spinner fa-spin text-info"></i>
                    </span>
                </div>
                <span class="document-status status-missing">Missing</span>
            </div>
            <div class="mt-2">
                <input class="form-control document-file" type="file" id="${fileInputName}" name="${fileInputName}" 
                       accept=".pdf,.docx,.doc,.jpg,.jpeg,.png,.gif,.bmp,.webp,.tiff"
                       data-doc-code="${docCode}" data-doc-name="${docName}">
                <small class="text-muted d-block mt-1">Upload the scanned copy or photo of the document.</small>
                <small class="validation-message text-muted d-block mt-1" style="display: none;"></small>
            </div>
        `;
        
        documentsList.appendChild(docItem);
    });
    
    // Attach validation listeners to the newly added file inputs
    attachFileValidationListeners();
    
    const modal = new bootstrap.Modal(document.getElementById('updateDocumentsModal'));
    modal.show();
}

// Attach file validation listeners
function attachFileValidationListeners() {
    const fileInputs = document.querySelectorAll('.document-file');
    
    fileInputs.forEach(input => {
        // Remove any existing listeners by cloning
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);
        
        // Add single change listener
        newInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            const docItem = e.target.closest('.document-item');
            const docCode = e.target.dataset.docCode;
            const docName = e.target.dataset.docName;
            const statusElement = docItem.querySelector('.document-status');
            const validationIndicator = docItem.querySelector('.validation-indicator-inline');
            const validationMessage = docItem.querySelector('.validation-message');
            
            if (!file) {
                // Reset if no file selected
                docItem.removeAttribute('data-valid');
                docItem.removeAttribute('data-filename');
                statusElement.textContent = 'Missing';
                statusElement.className = 'document-status status-missing';
                validationIndicator.style.display = 'none';
                validationMessage.style.display = 'none';
                docItem.classList.remove('valid-document', 'invalid-document', 'validating');
                return;
            }
            
            // Show validating state
            docItem.classList.add('validating');
            docItem.classList.remove('valid-document', 'invalid-document');
            statusElement.textContent = 'Validating...';
            statusElement.className = 'document-status status-validating';
            validationIndicator.style.display = 'inline-block';
            validationIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-info"></i> Validating...';
            validationMessage.style.display = 'none';
            
            // Store filename
            docItem.setAttribute('data-filename', file.name);
            
            try {
                // Validate the file
                const result = await window.validateFileForUpload(file, docCode, docItem);
                
                // Update UI based on validation result
                docItem.classList.remove('validating');
                
                if (result.valid && result.confidence >= 70) {
                    // Valid document - no confirmation needed
                    docItem.classList.add('valid-document');
                    docItem.setAttribute('data-valid', 'true');
                    statusElement.textContent = 'Valid';
                    statusElement.className = 'document-status status-valid';
                    validationIndicator.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${file.name}`;
                    validationIndicator.style.display = 'inline-block';
                    validationMessage.textContent = result.message;
                    validationMessage.className = 'validation-message text-success d-block mt-1';
                    validationMessage.style.display = 'block';
                    
                } else if (result.confidence >= 45) {
                    // Borderline - show confirmation dialog
                    const confirmResult = await Swal.fire({
                        icon: 'warning',
                        title: 'Document Validation Uncertain',
                        html: `
                            <div style="text-align: left;">
                                <p style="margin-bottom: 1rem;">
                                    <strong>File:</strong> ${file.name}<br>
                                    <strong>Expected:</strong> ${docName}<br>
                                    <strong>Confidence:</strong> ${result.confidence}%
                                </p>
                                <p style="background-color: #fff3cd; padding: 1rem; border-left: 4px solid #ffc107; border-radius: 4px;">
                                    ${result.message || 'Document validation uncertain - will be reviewed'}
                                </p>
                                <p style="margin-top: 1rem; color: #666;">
                                    Are you sure you want to upload this document?
                                </p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#800000',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Upload Anyway',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    });

                    if (confirmResult.isConfirmed) {
                        // User confirmed - accept with warning
                        docItem.classList.add('valid-document');
                        docItem.setAttribute('data-valid', 'warning');
                        statusElement.textContent = 'Accepted';
                        statusElement.className = 'document-status status-validating';
                        validationIndicator.innerHTML = `<i class="fas fa-exclamation-triangle text-warning"></i> ${file.name}`;
                        validationIndicator.style.display = 'inline-block';
                        validationMessage.textContent = 'Accepted with warning - will be reviewed';
                        validationMessage.className = 'validation-message text-warning d-block mt-1';
                        validationMessage.style.display = 'block';
                    } else {
                        // User cancelled - reset file input
                        e.target.value = '';
                        docItem.removeAttribute('data-valid');
                        docItem.removeAttribute('data-filename');
                        statusElement.textContent = 'Missing';
                        statusElement.className = 'document-status status-missing';
                        validationIndicator.style.display = 'none';
                        validationMessage.style.display = 'none';
                        docItem.classList.remove('valid-document', 'invalid-document');
                    }
                    
                } else {
                    // Invalid document - show confirmation dialog
                    const confirmResult = await Swal.fire({
                        icon: 'error',
                        title: 'Invalid Document Detected',
                        html: `
                            <div style="text-align: left;">
                                <p style="margin-bottom: 1rem;">
                                    <strong>File:</strong> ${file.name}<br>
                                    <strong>Expected:</strong> ${docName}<br>
                                    <strong>Confidence:</strong> ${result.confidence}%
                                </p>
                                <p style="background-color: #ffe6e6; padding: 1rem; border-left: 4px solid #dc3545; border-radius: 4px;">
                                    ${result.message || 'Validation failed - document does not match requirements'}
                                </p>
                                <p style="margin-top: 1rem; color: #666;">
                                    <strong>This document appears to be incorrect.</strong><br>
                                    Are you absolutely sure you want to upload it anyway?
                                </p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Upload Anyway',
                        cancelButtonText: 'Cancel & Choose Another',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    });

                    if (confirmResult.isConfirmed) {
                        // User confirmed - accept but mark as invalid
                        docItem.classList.add('invalid-document');
                        docItem.setAttribute('data-valid', 'forced');
                        statusElement.textContent = 'Forced Upload';
                        statusElement.className = 'document-status status-invalid';
                        validationIndicator.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> ${file.name}`;
                        validationIndicator.style.display = 'inline-block';
                        validationMessage.textContent = 'Uploaded despite validation failure - requires manual review';
                        validationMessage.className = 'validation-message text-danger d-block mt-1';
                        validationMessage.style.display = 'block';
                    } else {
                        // User cancelled - reset file input
                        e.target.value = '';
                        docItem.removeAttribute('data-valid');
                        docItem.removeAttribute('data-filename');
                        statusElement.textContent = 'Missing';
                        statusElement.className = 'document-status status-missing';
                        validationIndicator.style.display = 'none';
                        validationMessage.style.display = 'none';
                        docItem.classList.remove('valid-document', 'invalid-document');
                    }
                }
                
            } catch (error) {
                console.error('Validation error:', error);
                docItem.classList.remove('validating');
                docItem.classList.add('invalid-document');
                docItem.setAttribute('data-valid', 'error');
                statusElement.textContent = 'Error';
                statusElement.className = 'document-status status-invalid';
                validationIndicator.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> ${file.name}`;
                validationIndicator.style.display = 'inline-block';
                validationMessage.textContent = 'Validation error: ' + error.message;
                validationMessage.className = 'validation-message text-danger d-block mt-1';
                validationMessage.style.display = 'block';
            }
        });
    });
}

// Enhanced client-side document validation (same as newstudent.html)
(async ()=>{
    // Utility: Levenshtein + similarity
    function levenshtein(a,b){
        a = a||''; b = b||'';
        const m=a.length, n=b.length;
        if(!m) return n; if(!n) return m;
        const dp = Array.from({length:m+1},()=> new Array(n+1).fill(0));
        for(let i=0;i<=m;i++) dp[i][0]=i;
        for(let j=0;j<=n;j++) dp[0][j]=j;
        for(let i=1;i<=m;i++){
            for(let j=1;j<=n;j++){
                const cost = a[i-1]===b[j-1]?0:1;
                dp[i][j]=Math.min(dp[i-1][j]+1, dp[i][j-1]+1, dp[i-1][j-1]+cost);
            }
        }
        return dp[m][n];
    }
    function similarity(a,b){
        if(!a||!b) return 0;
        const maxLen = Math.max(a.length,b.length);
        const dist = levenshtein(a,b);
        return Math.max(0, 1 - dist / maxLen);
    }
    function normalizeTextForMatching(text){
        if(!text) return '';
        let s = text.normalize('NFKD').replace(/\r\n/g,' ').replace(/\n/g,' ').replace(/\s+/g,' ');
        s = s.replace(/form[\s\-:]*1?38/ig,'form 138');
        s = s.replace(/\btor\b/ig,'transcript of records');
        s = s.replace(/\bpsa\b/ig,'psa');
        s = s.replace(/ﬁ/g,'fi');
        return s.toLowerCase();
    }

    // Preprocess image -> blob URL (grayscale + contrast)
    async function preprocessImageFile(file, maxWidth = 1800){
        return await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const scale = Math.min(1, maxWidth / img.width);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img,0,0,canvas.width,canvas.height);

                // grayscale + contrast stretch
                const id = ctx.getImageData(0,0,canvas.width,canvas.height);
                const d = id.data;
                for(let i=0;i<d.length;i+=4){
                    const r=d[i], g=d[i+1], b=d[i+2];
                    let gray = 0.299*r + 0.587*g + 0.114*b;
                    gray = 128 + 1.05*(gray-128);
                    gray = Math.max(0, Math.min(255, gray));
                    d[i]=d[i+1]=d[i+2]=gray;
                }
                ctx.putImageData(id,0,0);
                canvas.toBlob(blob => {
                    const url = URL.createObjectURL(blob);
                    resolve({ blob, url });
                }, 'image/jpeg', 0.92);
            };
            img.onerror = () => reject(new Error('Image load failed during preprocessing'));
            img.src = URL.createObjectURL(file);
        });
    }

    // OCR image (Tesseract)
    async function ocrImageWithTesseract(blobUrl, updateCallback){
        const res = await Tesseract.recognize(blobUrl, 'eng', {
            logger: m => {
                if(typeof updateCallback === 'function' && m.status === 'recognizing text'){
                    updateCallback(Math.round(m.progress*100), m.status);
                }
            },
            tessedit_pageseg_mode: Tesseract.PSM.AUTO,
            oem: 1
        });
        return (res?.data?.text) || '';
    }

    // Extract text from PDF using pdf.js, else render & OCR
    async function extractTextFromPDF(file, updateProgress){
        const arrayBuffer = await file.arrayBuffer();
        const loadingTask = pdfjsLib.getDocument({data: arrayBuffer});
        const pdf = await loadingTask.promise;
        let fullText = '';
        const pages = Math.min(pdf.numPages, 6);
        for(let i=1;i<=pages;i++){
            const page = await pdf.getPage(i);
            const content = await page.getTextContent();
            fullText += content.items.map(it=>it.str).join(' ') + ' ';
        }
        fullText = normalizeTextForMatching(fullText);
        if(fullText.length > 60) return fullText;

        // Fallback to OCR
        let ocrText = '';
        for(let p=1; p<=Math.min(pdf.numPages,2); p++){
            const page = await pdf.getPage(p);
            const viewport = page.getViewport({scale:2.0});
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(viewport.width);
            canvas.height = Math.round(viewport.height);
            const ctx = canvas.getContext('2d');
            await page.render({canvasContext: ctx, viewport}).promise;
            const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.9));
            const fakeFile = new File([blob], `page${p}.jpg`, {type:'image/jpeg'});
            const { url } = await preprocessImageFile(fakeFile, 2000);
            const t = await ocrImageWithTesseract(url, (prog)=> updateProgress && updateProgress(prog,'ocr'));
            URL.revokeObjectURL(url);
            ocrText += t + ' ';
        }
        return normalizeTextForMatching(ocrText);
    }

    // Extract text from image file (preprocess -> ocr)
    async function extractTextFromImage(file, updateProgress){
        const { url } = await preprocessImageFile(file, 2000);
        const t = await ocrImageWithTesseract(url, (prog)=> updateProgress && updateProgress(prog, 'ocr'));
        URL.revokeObjectURL(url);
        return normalizeTextForMatching(t);
    }

    // Extract text from docx using mammoth (browser)
    async function extractTextFromDocx(file){
        const arrayBuffer = await file.arrayBuffer();
        const res = await mammoth.extractRawText({ arrayBuffer });
        return normalizeTextForMatching(res.value || '');
    }

    // Keyword mappings (mirror server)
    const KEYWORD_MAPPINGS = {
        'card138': {
            primary_keywords: ['card', 'report', 'grade', 'school', 'subjects', 'form 138', 'form 137', '138', 'student', 'record'],
            secondary_keywords: ['enrollment', 'semester', 'year', 'name', 'course'],
            negative_keywords: ['birth certificate', 'marriage certificate', 'transcript'],
            min_primary: 1, min_total: 2, document_name: 'Card 138'
        },
        'moral': {
            primary_keywords: ['good moral','moral character','character certificate','good conduct','moral','character','conduct'],
            secondary_keywords: ['behavior','discipline','ethics','reputation','good','certificate'],
            negative_keywords: ['birth','marriage','transcript'],
            min_primary:1,min_total:1, document_name: 'Certificate of Good Moral'
        },
        'birth': {
            primary_keywords: ['birth certificate','birth record','civil registry','psa','birth','certificate', 'live birth', 'certificate of live birth'],
            secondary_keywords: ['born','registry','civil','government'],
            negative_keywords: ['marriage','moral','transcript'],
            min_primary:1, min_total:1, document_name: 'PSA Birth Certificate'
        },
        'marriage': {
            primary_keywords: ['marriage certificate','certificate of marriage','marriage contract','marriage','wedding'],
            secondary_keywords: ['married','spouse','civil registry'],
            negative_keywords: ['birth','moral','transcript'],
            min_primary:1,min_total:1, document_name: 'PSA Marriage Certificate'
        },
        'tor': {
            primary_keywords: ['transcript of records','transcript','tor','academic record'],
            secondary_keywords: ['grades','subjects','units','semester','gpa','course'],
            negative_keywords: ['birth','marriage','moral','dismissal'],
            min_primary:1,min_total:3, document_name: 'Transcript of Records'
        },
        'honorable': {
            primary_keywords: ['honorable dismissal','transfer credential','dismissal'],
            secondary_keywords: ['good standing','transfer','clearance','credential'],
            negative_keywords: ['birth','marriage','moral','transcript'],
            min_primary:1,min_total:2, document_name: 'Honorable Dismissal'
        },
        'gradeslip': {
            primary_keywords: ['grade slip','report card','grade report','academic report'],
            secondary_keywords: ['grades','semester','final grade','academic performance','subjects'],
            negative_keywords: ['birth','marriage','moral','dismissal'],
            min_primary:1,min_total:3, document_name: 'Grade Slip'
        },
        'id': { skip_validation: true, document_name: '2x2 Picture' }
    };

    // Enhanced validation
    function performEnhancedValidation(text, docType, file){
        if (text.length < 50) return { valid: false, confidence: 20, message: 'Insufficient text extracted' };

        const config = KEYWORD_MAPPINGS[docType];
        if(!config) return { valid:true, confidence:70, message:'No rules configured' };
        if(config.skip_validation){
            return { valid: true, confidence: 100, message: 'Image accepted (no text validation)' };
        }

        const filename = file.name.toLowerCase().replace(/\s+/g, '');
        const foundInFilename = [];
        
        for(const kw of (config.primary_keywords || [])){
            const cleanKw = kw.replace(/\s+/g, '').toLowerCase();
            if(filename.includes(cleanKw)){
                foundInFilename.push(kw);
            }
        }
        
        if(foundInFilename.length > 0){
            return {
                valid: true,
                confidence: 85,
                message: `✓ Valid ${config.document_name} - filename match: ${foundInFilename.join(', ')}`
            };
        }
        
        text = text || '';
        const lowerText = text.toLowerCase();

        let foundPrimary = [];
        for(const kw of (config.primary_keywords || [])){
            const safe = kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g,'\\s*');
            const re = new RegExp('\\b' + safe + '\\b','i');
            if(re.test(lowerText)) foundPrimary.push(kw);
            else {
                const parts = lowerText.split(/\s+/);
                for(let i=0;i<parts.length;i++){
                    const window = parts.slice(i, i+Math.min(4, kw.split(' ').length+1)).join(' ');
                    if(similarity(window, kw.toLowerCase()) > 0.78){
                        foundPrimary.push(kw + ' (fuzzy)');
                        break;
                    }
                }
            }
        }

        let foundSecondary = [];
        for(const kw of (config.secondary_keywords||[])){
            const safe = kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g,'\\s*');
            const re = new RegExp('\\b' + safe + '\\b','i');
            if(re.test(lowerText)) foundSecondary.push(kw);
            else if(similarity(lowerText.slice(0,200), kw.toLowerCase()) > 0.7) foundSecondary.push(kw + ' (fuzzy)');
        }

        const foundNegative = (config.negative_keywords||[]).filter(kw=>{
            const re = new RegExp('\\b' + kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&').replace(/\s+/g,'\\s*') + '\\b','i');
            return re.test(lowerText);
        });

        const hasPrimary = foundPrimary.length >= (config.min_primary || 1);
        const isValid = hasPrimary && foundNegative.length === 0;

        let confidence = 30;
        confidence += Math.min(60, foundPrimary.length * 35);
        confidence += Math.min(20, foundSecondary.length * 10);
        confidence += Math.min(20, Math.floor(Math.min(100, (text.length||0) / 200)));
        if(['pdf','docx'].includes((file.name.split('.').pop()||'').toLowerCase())) confidence += 6;
        confidence = Math.max(0, Math.min(100, confidence));

        if(foundNegative.length > 0 && foundPrimary.length === 0){
            return { valid:false, confidence:0, message: `✗ Wrong document type — contains ${foundNegative.join(', ')}`, negative_keywords: foundNegative };
        }

        let message = '';
        if(isValid && confidence >= 70) message = `✓ Valid ${config.document_name} — found: ${[...foundPrimary,...foundSecondary].join(', ')}`;
        else if(isValid) message = `⚠ Borderline ${config.document_name} — confidence ${confidence}% (accepted due to keyword match)`;
        else if(!hasPrimary) message = `✗ Invalid ${config.document_name} — missing primary keywords`;
        else message = `✗ Validation failed (${confidence}%)`;

        return { valid: isValid, confidence, message, keywords_found: [...foundPrimary, ...foundSecondary], negative_keywords: foundNegative };
    }

    // Public function used by UI to validate a single file
    async function validateFileForUpload(file, docType, uploadItem, progressCallback){
        try{
            if(docType === 'id' || (KEYWORD_MAPPINGS[docType] && KEYWORD_MAPPINGS[docType].skip_validation)){
                if(/image\//i.test(file.type)){
                    return { valid:true, confidence:100, message: 'Image accepted' };
                } else {
                    return { valid:false, confidence:0, message: 'Expected image file for photo' };
                }
            }

            const ext = (file.name.split('.').pop()||'').toLowerCase();
            let text = '';

            if(ext === 'pdf'){
                if(progressCallback) progressCallback(10,'reading pdf');
                text = await extractTextFromPDF(file, (p, status) => { if(progressCallback) progressCallback(10 + Math.round(p*0.7), status); });
            } else if(ext === 'docx'){
                if(progressCallback) progressCallback(10,'reading docx');
                text = await extractTextFromDocx(file);
            } else if(/jpe?g|png|bmp|tiff|webp|gif/i.test(ext)){
                if(progressCallback) progressCallback(10,'preprocessing image');
                text = await extractTextFromImage(file, (p,status)=>{ if(progressCallback) progressCallback(10 + Math.round(p*0.8), status); });
            } else {
                text = '';
            }

            // UPDATED: More lenient text extraction check
            if(!text || text.length < 20){
                // Check filename as fallback
                const config = KEYWORD_MAPPINGS[docType];
                if (config) {
                    const filename = file.name.toLowerCase().replace(/\s+/g, '');
                    for(const kw of (config.primary_keywords || [])){
                        const cleanKw = kw.replace(/\s+/g, '').toLowerCase();
                        if(filename.includes(cleanKw)){
                            return { 
                                valid: true, 
                                confidence: 75, 
                                message: `✓ Valid ${config.document_name} - filename match (text extraction failed)` 
                            };
                        }
                    }
                }
                return { 
                    valid: false, 
                    confidence: 35, 
                    message: 'Insufficient text extracted — please ensure document is clear and readable' 
                };
            }

            const result = performEnhancedValidation(text, docType, file);
            return result;
        } catch(err){
            console.error('validateFileForUpload error:', err);
            return { valid:false, confidence:40, message: 'Validation error: ' + err.message };
        }
    }

    // Export to window scope
    window.validateFileForUpload = validateFileForUpload;
    window.improvedDocValidator = {
        validateFileForUpload,
        performEnhancedValidation,
        KEYWORD_MAPPINGS
    };
})();

// DOMContentLoaded event handler
document.addEventListener("DOMContentLoaded", function () {
    // AJAX form submission handler
    const updateForm = document.getElementById('updateDocumentsForm');
    if (updateForm) {
        updateForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Show loading
            Swal.fire({
                title: 'Uploading Documents...',
                html: 'Please wait while we upload and validate your documents.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const formData = new FormData(this);
                
                // FIXED: Determine correct URL based on whether we're in staff or admin view
                const isStaffView = window.isStaffView || false;
                const submitUrl = isStaffView 
                    ? '../AdminAccount/lackingofdoc_logic.php' 
                    : 'lackingofdoc_logic.php';
                
                console.log('Submitting to:', submitUrl); // Debug log
                
                const response = await fetch(submitUrl, {
                    method: 'POST',
                    body: formData
                });

                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Server returned non-JSON response. Check server logs.");
                }

                const result = await response.json();
                console.log('Server response:', result); // Debug log

                Swal.close();

                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Upload Successful!',
                        html: (result.message || 'Documents uploaded successfully').replace(/\n/g, '<br>'),
                        confirmButtonColor: '#800000',
                        timer: 5000,
                        timerProgressBar: true
                    });
                    
                    // Close modal and reload page
                    const modal = bootstrap.Modal.getInstance(document.getElementById('updateDocumentsModal'));
                    if (modal) modal.hide();
                    window.location.reload();
                    
                } else if (result.isDuplicate) {
                    // Format duplicate files message
                    let duplicateList = '';
                    if (Array.isArray(result.duplicateFiles)) {
                        duplicateList = result.duplicateFiles.map(file => `<li>${file}</li>`).join('');
                    } else {
                        duplicateList = `<li>${result.message}</li>`;
                    }
                    
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Duplicate File Detected',
                        html: `
                            <div style="text-align: left; padding: 1rem;">
                                <p style="margin-bottom: 1rem; font-weight: 600; color: #856404;">
                                    The following file(s) have already been uploaded:
                                </p>
                                <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; border-radius: 4px;">
                                    <ul style="margin: 0; padding-left: 1.5rem;">
                                        ${duplicateList}
                                    </ul>
                                </div>
                                <p style="margin-top: 1rem; color: #666; font-size: 0.95rem;">
                                    <i class="fas fa-info-circle"></i> Please verify that you're uploading the correct file or remove the duplicate from the system first.
                                </p>
                            </div>
                        `,
                        confirmButtonColor: '#800000',
                        confirmButtonText: 'I Understand',
                        width: '600px',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    });
                } else {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        html: (result.message || 'An error occurred during upload.').replace(/\n/g, '<br>'),
                        confirmButtonColor: '#800000'
                    });
                }

            } catch (error) {
                Swal.close();
                console.error('Upload error:', error);
                await Swal.fire({
                    icon: 'error',
                    title: 'Upload Error',
                    html: 'An unexpected error occurred. Please try again.<br><small>' + error.message + '</small>',
                    confirmButtonColor: '#800000'
                });
            }
        });
    }
});
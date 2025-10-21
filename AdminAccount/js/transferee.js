/*
  Improved client-side validator for Transferee form
  - Preprocess images via canvas before Tesseract
  - Use pdf.js text extraction then fallback to rendered-page OCR
  - Normalization, regex and fuzzy matching
  - Returns result { valid, confidence, message }
  - Exposes validateFileForUpload(file, docType, uploadItem)
*/

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
  s = s.replace(/form[\s\-:]*1?38/ig,'form 138');      // form138 -> form 138
  s = s.replace(/\btor\b/ig,'transcript of records');
  s = s.replace(/\bpsa\b/ig,'psa');
  s = s.replace(/ﬁ/g,'fi'); // ligature fixes
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
        // slight contrast stretch
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

  // likely scanned PDF -> render first 2 pages to canvas and OCR
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

// Keyword mappings (mirror server) - UPDATED to match newstudent.html for consistency
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
    min_primary:1,
    min_total:1,
    document_name: 'PSA Birth Certificate'
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

  // Very lenient filename check first
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
  
  // If no filename match, check text content with relaxed rules
  text = text || '';
  const lowerText = text.toLowerCase();

  let foundPrimary = [];
  for(const kw of (config.primary_keywords || [])){
    const safe = kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g,'\\s*');
    const re = new RegExp('\\b' + safe + '\\b','i');
    if(re.test(lowerText)) foundPrimary.push(kw);
    else {
      // fuzzy search: check similarity of windows
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
    const safe = kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&').replace(/\s+/g,'\\s*');
    const re = new RegExp('\\b' + safe + '\\b','i');
    if(re.test(lowerText)) foundSecondary.push(kw);
    else if(similarity(lowerText.slice(0,200), kw.toLowerCase()) > 0.7) foundSecondary.push(kw + ' (fuzzy)');
  }

  const foundNegative = (config.negative_keywords||[]).filter(kw=>{
    const re = new RegExp('\\b' + kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&').replace(/\s+/g,'\\s*') + '\\b','i');
    return re.test(lowerText);
  });

  // RELAXED VALIDATION: Accept if primary keywords found and no negatives (ignore total keyword count)
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
    // photo shortcut
    if(docType === 'photo' || (KEYWORD_MAPPINGS[docType] && KEYWORD_MAPPINGS[docType].skip_validation)){
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
    } else if(/jpe?g|png|bmp|tiff|webp/i.test(ext)){
      if(progressCallback) progressCallback(10,'preprocessing image');
      text = await extractTextFromImage(file, (p,status)=>{ if(progressCallback) progressCallback(10 + Math.round(p*0.8), status); });
    } else {
      // unsupported type - fallback to filename
      text = '';
    }

    if(!text || text.length < 20){
      // Return low confidence for insufficient text - require manual review
      return { valid:false, confidence:30, message: 'Insufficient text extracted — manual review required' };
    }

    const result = performEnhancedValidation(text, docType, file);
    return result;
  } catch(err){
    console.error('validateFileForUpload error:', err);
    return { valid:false, confidence:40, message: 'Client-side validation error — will validate on server' };
  }
}

// Enhanced UI handlers for your existing markup
function showValidatingUI(uploadItem) {
    uploadItem.classList.remove('uploaded', 'error', 'warning');
    uploadItem.classList.add('validating');
    
    const uploadIcon = uploadItem.querySelector('.upload-icon i');
    const uploadText = uploadItem.querySelector('.upload-text');
    
    if (uploadIcon) {
        uploadIcon.className = 'fas fa-spinner fa-spin';
        uploadIcon.style.color = '#17a2b8';
    }
    if (uploadText) {
        uploadText.textContent = 'Validating document...';
    }
    
    // Remove existing status
    const existingStatus = uploadItem.querySelector('.validation-status');
    if (existingStatus) {
        existingStatus.remove();
    }
}

function showSuccessUI(uploadItem, file, result) {
    const uploadIcon = uploadItem.querySelector('.upload-icon i');
    const uploadText = uploadItem.querySelector('.upload-text');
    let fileInfo = uploadItem.querySelector('.file-info');
    
    if (!fileInfo) {
        fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        uploadItem.appendChild(fileInfo);
    }
    
    uploadItem.classList.remove('validating', 'error', 'warning');
    uploadItem.classList.add('uploaded');
    
    if (uploadIcon) {
        uploadIcon.className = 'fas fa-check-circle';
        uploadIcon.style.color = '#28a745';
    }
    
    if (uploadText) {
        uploadText.innerHTML = `<strong style="color: #28a745;">${file.name}</strong>`;
    }
    
    fileInfo.innerHTML = `
        <div style="color: #28a745; font-weight: 600; text-align: center; font-size: 0.8rem;">
            ✓ Valid Document (${result.confidence}% confidence)
        </div>
    `;
    fileInfo.style.display = 'block';
    
    const progressContainer = uploadItem.querySelector('.validation-progress');
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
    
    const statusIndicator = document.createElement('div');
    statusIndicator.className = 'validation-status valid';
    statusIndicator.innerHTML = '<i class="fas fa-check"></i>';
    statusIndicator.title = result.message || 'Valid document';
    uploadItem.appendChild(statusIndicator);
}

function showErrorUI(uploadItem, file, message) {
    const uploadIcon = uploadItem.querySelector('.upload-icon i');
    const uploadText = uploadItem.querySelector('.upload-text');
    let fileInfo = uploadItem.querySelector('.file-info');
    
    if (!fileInfo) {
        fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        uploadItem.appendChild(fileInfo);
    }
    
    uploadItem.classList.remove('validating', 'uploaded', 'warning');
    uploadItem.classList.add('error');
    
    if (uploadIcon) {
        uploadIcon.className = 'fas fa-times-circle';
        uploadIcon.style.color = '#dc3545';
    }
    
    if (uploadText) {
        uploadText.innerHTML = `<strong style="color: #dc3545;">Invalid Document</strong>`;
    }
    
    fileInfo.innerHTML = `
        <div style="color: #dc3545; font-weight: 600; text-align: center; font-size: 0.75rem;">
            ✗ ${message}
        </div>
    `;
    fileInfo.style.display = 'block';
    
    const progressContainer = uploadItem.querySelector('.validation-progress');
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
    
    const statusIndicator = document.createElement('div');
    statusIndicator.className = 'validation-status invalid';
    statusIndicator.innerHTML = '<i class="fas fa-times"></i>';
    statusIndicator.title = message;
    uploadItem.appendChild(statusIndicator);
}

function showWarningUI(uploadItem, file, message) {
    const uploadIcon = uploadItem.querySelector('.upload-icon i');
    const uploadText = uploadItem.querySelector('.upload-text');
    let fileInfo = uploadItem.querySelector('.file-info');
    
    if (!fileInfo) {
        fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        uploadItem.appendChild(fileInfo);
    }
    
    uploadItem.classList.remove('validating', 'error');
    uploadItem.classList.add('uploaded', 'warning');
    
    if (uploadIcon) {
        uploadIcon.className = 'fas fa-exclamation-triangle';
        uploadIcon.style.color = '#ffc107';
    }
    
    if (uploadText) {
        uploadText.innerHTML = `<strong style="color: #856404;">${file.name}</strong>`;
    }
    
    fileInfo.innerHTML = `
        <div style="color: #856404; font-weight: 600; text-align: center; font-size: 0.75rem;">
            ⚠ ${message}
        </div>
    `;
    fileInfo.style.display = 'block';
    
    const progressContainer = uploadItem.querySelector('.validation-progress');
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
    
    const statusIndicator = document.createElement('div');
    statusIndicator.className = 'validation-status warning';
    statusIndicator.innerHTML = '<i class="fas fa-exclamation"></i>';
    statusIndicator.title = message;
    uploadItem.appendChild(statusIndicator);
}

// Hook UI upload-items automatically
function attachUploadItems(){
  const uploadItems = document.querySelectorAll('.upload-item');
  uploadItems.forEach(item=>{
    const input = item.querySelector('input[type=file]');
    const docType = item.dataset.docType || item.getAttribute('data-doc-type') || item.getAttribute('data-type');
    if(!input) return;
    
    // click the upload box to choose file
    item.addEventListener('click', (e)=>{
      // ignore click if disabled or clicking checkbox/label
      if(item.classList.contains('disabled') || input.disabled) return;
      if(e.target.type === 'checkbox' || e.target.tagName === 'LABEL' || e.target.closest('.marriage-required-container')) {
        return;
      }
      input.click();
    });
    
    input.addEventListener('change', async (ev)=>{
      const f = input.files && input.files[0];
      if(!f) return;
      
      // show validating UI
      showValidatingUI(item);
      
      // progress bar update
      const progressBar = item.querySelector('.validation-progress-bar');
      const progressContainer = item.querySelector('.validation-progress');
      if (progressContainer) progressContainer.style.display = 'block';
      
      const progressUpdate = (p, status) => {
        if(progressBar) progressBar.style.width = Math.min(100, Math.max(0,p)) + '%';
        const uploadText = item.querySelector('.upload-text');
        if (uploadText) uploadText.textContent = `${status || 'Processing'}... ${p}%`;
      };
      
      // Basic client-side checks
      if (f.size > 10 * 1024 * 1024) {
        showErrorUI(item, f, 'File too large (max 10MB)');
        return;
      }
      
      const res = await validateFileForUpload(f, docType, item, progressUpdate);
      
      // update UI based on res
      if(res.valid && res.confidence >= 70){
        showSuccessUI(item, f, res);
        item.setAttribute('data-valid','true');
      } else if(res.confidence >= 45){
        showWarningUI(item, f, res.message || 'Document validation uncertain');
        item.setAttribute('data-valid','true'); // Allow borderline cases
      } else {
        showErrorUI(item, f, res.message || 'Validation failed');
        item.setAttribute('data-valid','false');
      }
      
      item.setAttribute('data-validation-message', res.message || '');
    });
  });
}

// run attach on DOM ready
if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', attachUploadItems);
} else {
  attachUploadItems();
}

// export to window for other scripts
window.validateFileForUpload = validateFileForUpload;
window.improvedDocValidator = {
  validateFileForUpload,
  performEnhancedValidation,
  KEYWORD_MAPPINGS
};

})(); // IIFE

// Document-specific logic (marriage certificate toggle, form submission, etc.)
document.addEventListener('DOMContentLoaded', () => {
    // Marriage certificate logic for transferee form
    const marriageCheckbox = document.getElementById('marriageRequiredTransferee');
    if (marriageCheckbox) {
        const marriageUpload = document.querySelector('.marriage-upload');
        const marriageFileInput = marriageUpload?.querySelector('input[type="file"]');
        const marriageUploadText = marriageUpload?.querySelector('.upload-text');

        marriageCheckbox.addEventListener('change', function() {
            if (this.checked) {
                marriageUpload.classList.remove('disabled');
                marriageFileInput.disabled = false;
                marriageUploadText.textContent = 'Click to upload';
                marriageUpload.style.pointerEvents = 'auto';
            } else {
                marriageUpload.classList.add('disabled');
                marriageFileInput.disabled = true;
                marriageFileInput.value = '';
                marriageUploadText.textContent = 'Check "Required" to enable upload';
                marriageUpload.style.pointerEvents = 'none';
                
                // Reset upload state
                marriageUpload.classList.remove('uploaded', 'validating', 'error', 'warning');
                const uploadIcon = marriageUpload.querySelector('.upload-icon i');
                uploadIcon.className = 'fas fa-heart';
                uploadIcon.style.color = '';
                
                const validationStatus = marriageUpload.querySelector('.validation-status');
                const fileInfo = marriageUpload.querySelector('.file-info');
                if (validationStatus) validationStatus.remove();
                if (fileInfo) fileInfo.style.display = 'none';
            }
        });
    }

    // Enhanced form submission handling - ONLY VALIDATE STUDENT INFO TEXT FIELDS
    const form = document.getElementById('transfereeForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // ONLY check required student information text/select fields (NOT file inputs)
            const studentID = document.querySelector('input[name="studentID"]');
            const firstName = document.querySelector('input[name="firstName"]');
            const lastName = document.querySelector('input[name="lastName"]');
            const course = document.querySelector('select[name="studentCourse"]');
            const yearLevel = document.querySelector('select[name="yearLevel"]');

            let missingFields = [];

            // Check only the 5 essential fields
            if (!studentID || !studentID.value.trim()) {
                missingFields.push('Student ID');
                if (studentID) {
                    studentID.style.borderColor = '#dc3545';
                    studentID.style.backgroundColor = '#fff5f5';
                }
            } else if (studentID) {
                studentID.style.borderColor = '';
                studentID.style.backgroundColor = '';
            }

            if (!firstName || !firstName.value.trim()) {
                missingFields.push('First Name');
                if (firstName) {
                    firstName.style.borderColor = '#dc3545';
                    firstName.style.backgroundColor = '#fff5f5';
                }
            } else if (firstName) {
                firstName.style.borderColor = '';
                firstName.style.backgroundColor = '';
            }

            if (!lastName || !lastName.value.trim()) {
                missingFields.push('Last Name');
                if (lastName) {
                    lastName.style.borderColor = '#dc3545';
                    lastName.style.backgroundColor = '#fff5f5';
                }
            } else if (lastName) {
                lastName.style.borderColor = '';
                lastName.style.backgroundColor = '';
            }

            if (!course || !course.value) {
                missingFields.push('Course');
                if (course) {
                    course.style.borderColor = '#dc3545';
                    course.style.backgroundColor = '#fff5f5';
                }
            } else if (course) {
                course.style.borderColor = '';
                course.style.backgroundColor = '';
            }

            if (!yearLevel || !yearLevel.value) {
                missingFields.push('Year Level');
                if (yearLevel) {
                    yearLevel.style.borderColor = '#dc3545';
                    yearLevel.style.backgroundColor = '#fff5f5';
                }
            } else if (yearLevel) {
                yearLevel.style.borderColor = '';
                yearLevel.style.backgroundColor = '';
            }

            if (missingFields.length > 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Information',
                    html: `Please fill in the following required fields:<br><br><strong>${missingFields.join('<br>')}</strong>`,
                    confirmButtonColor: '#800000'
                });
                return false;
            }

            // OPTIONAL: Collect validation warnings for uploaded documents (but don't block submission)
            const warningUploads = document.querySelectorAll('.upload-item.warning');
            const errorUploads = document.querySelectorAll('.upload-item.error');
            
            // Show warning dialog but allow submission
            if (warningUploads.length > 0 || errorUploads.length > 0) {
                const problematicDocuments = [];
                
                [...warningUploads, ...errorUploads].forEach(item => {
                    const title = item.querySelector('.upload-title')?.textContent || 'Unknown';
                    const message = item.getAttribute('data-validation-message') || 'Could not verify document';
                    problematicDocuments.push(`<strong>${title}</strong>: ${message}`);
                });
                
                const result = await Swal.fire({
                    icon: 'warning',
                    title: 'Document Validation Warning',
                    html: `
                        <p style="margin-bottom: 1rem;">The following documents could not be fully validated:</p>
                        <ul style="text-align: left; margin: 1rem 0; padding-left: 2rem;">
                            ${problematicDocuments.map(doc => `<li>${doc}</li>`).join('')}
                        </ul>
                        <p style="margin-top: 1rem; font-weight: bold; color: #856404;">
                            Do you want to proceed?
                        </p>
                        <p style="font-size: 0.9rem; color: #666;">
                            The documents will be uploaded and flagged for manual review.
                        </p>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#800000',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Submit Anyway',
                    cancelButtonText: 'No, Let Me Fix It',
                    customClass: {
                        popup: 'swal-wide'
                    }
                });
                
                if (!result.isConfirmed) {
                    return false;
                }
            }

            // Show loading alert
            Swal.fire({
                title: 'Adding Transferee...',
                text: 'Please wait while we process the information',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit form via AJAX
            try {
                const formData = new FormData(this);
                
                // Determine the correct action URL
                let actionUrl = this.getAttribute('action') || 'transferee.php';
                
                const response = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.status === 'success') {
                    // Show success message with PROPER STYLING (GREEN)
                    let successMessage = result.message;
                    
                    if (result.upload_results && result.upload_results.length > 0) {
                        successMessage += '<br><br><strong>Documents uploaded:</strong><br>' + 
                                        result.upload_results.map(doc => `✓ ${doc}`).join('<br>');
                    }
                    
                    if (result.upload_errors && result.upload_errors.length > 0) {
                        successMessage += '<br><br><strong style="color: #856404;">Notes:</strong><br>' + 
                                        result.upload_errors.map(err => `⚠ ${err}`).join('<br>');
                    }

                    await Swal.fire({
                        icon: 'success',
                        title: '<span style="color: #28a745;">🎉 Transferee Added Successfully!</span>',
                        html: `<div style="color: #000;">${successMessage}</div>`,
                        confirmButtonColor: '#28a745',
                        confirmButtonText: 'Add Another Transferee',
                        customClass: {
                            popup: 'swal-success-popup'
                        }
                    });

                    // Reset form
                    this.reset();
                    
                    // Reset upload states
                    document.querySelectorAll('.upload-item').forEach(item => {
                        item.classList.remove('uploaded', 'validating', 'error', 'warning');
                        const uploadIcon = item.querySelector('.upload-icon i');
                        const uploadText = item.querySelector('.upload-text');
                        const fileInfo = item.querySelector('.file-info');
                        const validationStatus = item.querySelector('.validation-status');
                        
                        if (uploadIcon) {
                            const title = item.querySelector('.upload-title')?.textContent || '';
                            if (title.includes('Moral')) uploadIcon.className = 'fas fa-star';
                            else if (title.includes('Birth')) uploadIcon.className = 'fas fa-id-card';
                            else if (title.includes('Marriage')) uploadIcon.className = 'fas fa-heart';
                            else if (title.includes('Transcript')) uploadIcon.className = 'fas fa-file-alt';
                            else if (title.includes('Honorable')) uploadIcon.className = 'fas fa-certificate';
                            else if (title.includes('Grade')) uploadIcon.className = 'fas fa-file-text';
                            else if (title.includes('Picture')) uploadIcon.className = 'fas fa-camera';
                            else uploadIcon.className = 'fas fa-cloud-upload-alt';
                            uploadIcon.style.color = '';
                        }
                        if (uploadText) uploadText.textContent = 'Click to upload';
                        if (fileInfo) fileInfo.style.display = 'none';
                        if (validationStatus) validationStatus.remove();
                        
                        item.removeAttribute('data-valid');
                        item.removeAttribute('data-validation-message');
                    });

                    // Reset marriage certificate checkbox
                    const marriageCheckbox = document.getElementById('marriageRequiredTransferee') || document.getElementById('marriageRequired');
                    if (marriageCheckbox) {
                        marriageCheckbox.checked = false;
                        marriageCheckbox.dispatchEvent(new Event('change'));
                    }

                } else if (result.type === 'duplicate_id' || result.type === 'duplicate_file') {
                    Swal.fire({
                        icon: 'error',
                        title: result.type === 'duplicate_id' ? 'Duplicate Student Found' : 'Duplicate File Detected',
                        html: `
                            <p style="font-size: 1.1rem; margin-bottom: 1rem;">
                                <strong>${result.message}</strong>
                            </p>
                            <p style="color: #666;">
                                ${result.type === 'duplicate_id' 
                                    ? 'This student is already registered in the system. Please verify the student information or contact the registrar if you believe this is an error.'
                                    : 'This file has already been uploaded to the system. Please use a different file or verify that this is not a duplicate submission.'}
                            </p>
                        `,
                        confirmButtonColor: '#800000',
                        confirmButtonText: 'OK, I Understand'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'An error occurred while adding the transferee.',
                        confirmButtonColor: '#800000'
                    });
                }
            } catch (error) {
                console.error('Submission error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not connect to the server. Please try again.',
                    confirmButtonColor: '#800000'
                });
            }
        });
    }
});
// Disable auto discover to prevent multiple initializations
if (typeof Dropzone !== 'undefined') {
    Dropzone.autoDiscover = false;
}

document.addEventListener('DOMContentLoaded', function() {
    const dropzoneElement = document.querySelector('#colis-import-dropzone');
    const importForm = document.querySelector('#colis-import-form');
    
    if (!dropzoneElement || !importForm) return;

    // Get the upload URL from the form action
    const uploadUrl = importForm.getAttribute('action');

    // Initialize Dropzone
    const myDropzone = new Dropzone("#colis-import-dropzone", {
        url: uploadUrl,
        paramName: "file",
        maxFiles: 1,
        maxFilesize: 5,
        acceptedFiles: ".xlsx,.xls,.csv",
        autoProcessQueue: true, // Process file immediately
        dictDefaultMessage: "", // Hide default text
        init: function() {
            const dz = this;
            const messageContainer = document.querySelector('#import-messages');
            const flashContainer = document.querySelector('#dynamic-flash-container');

            function showFlash(message) {
                if (!flashContainer) return;
                
                const flashHtml = `
                    <div class="flex items-center gap-3 p-4 rounded-xl shadow-lg transition-opacity duration-300" 
                         style="background-color: #e8fff3; border: 1px solid #50cd89; color: #50cd89;" 
                         role="alert">
                        <i class="ki-filled ki-check-circle text-xl"></i>
                        <div class="font-medium flex-1">
                            ${message}
                        </div>
                        <button type="button" class="ms-auto opacity-80 hover:opacity-100 transition-opacity" onclick="this.parentElement.remove()">
                            <i class="ki-filled ki-cross"></i>
                        </button>
                    </div>
                `;
                flashContainer.innerHTML = flashHtml;
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    const flash = flashContainer.firstElementChild;
                    if (flash) {
                        flash.style.opacity = '0';
                        setTimeout(() => flash.remove(), 300);
                    }
                }, 5000);
            }

            this.on("success", function(file, response) {
                console.log("Upload Success:", response);
                if (messageContainer) {
                    messageContainer.classList.add('hidden');
                    messageContainer.innerHTML = '';
                }
                
                if (response.success) {
                    showFlash(`Importation réussie ! ${response.importedCount} colis ont été importés avec succès.`);
                    
                    if (response.errors && response.errors.length > 0 && messageContainer) {
                        messageContainer.classList.remove('hidden');
                        messageContainer.innerHTML = `
                            <div class="p-4 rounded-lg bg-yellow-100 border border-yellow-200 text-yellow-800">
                                <p class="font-bold mb-1">Cependant, quelques erreurs ont été rencontrées :</p>
                                <ul class="list-disc list-inside text-sm">
                                    ${response.errors.map(err => `<li>${err}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }
                }
                
                // Remove file from dropzone after success to allow another upload
                setTimeout(() => { dz.removeFile(file); }, 2000);
            });

            this.on("error", function(file, message) {
                console.error("Upload Error:", message);
                if (!messageContainer) return;

                messageContainer.classList.remove('hidden');
                const errorMsg = typeof message === 'string' ? message : (message.error || "Une erreur est survenue lors de l'importation.");
                messageContainer.innerHTML = `
                    <div class="p-4 rounded-lg bg-red-100 border border-red-200 text-red-800">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="ki-filled ki-cross-circle text-xl"></i>
                            <span class="font-bold">Erreur d'importation</span>
                        </div>
                        <p>${errorMsg}</p>
                    </div>
                `;
            });
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('kt_calendar_app');
    if (!calendarEl) return;

    var eventsUrl = calendarEl.dataset.eventsUrl;
    var newUrl = calendarEl.dataset.newUrl;
    var moveUrlTemplate = calendarEl.dataset.moveUrlTemplate;

    function closeCalendarPopovers() {
        document.querySelectorAll('.fc-popover').forEach(function (popover) {
            popover.style.display = 'none';
        });
    }

    function showConfirmationModal(options) {
        var modalEl = document.getElementById('ramassage_confirm_modal');
        var titleEl = document.getElementById('ramassage_confirm_title');
        var messageEl = document.getElementById('ramassage_confirm_message');
        var submitBtn = document.getElementById('ramassage_confirm_submit');
        var cancelBtn = document.getElementById('ramassage_confirm_cancel');
        var closeBtn = document.getElementById('ramassage_confirm_close');

        var defaultMessage = options && options.message ? options.message : "Voulez-vous confirmer cette action ?";

        // Fallback if Metronic modal is unavailable on current page.
        if (!modalEl || typeof KTModal === 'undefined') {
            return Promise.resolve(window.confirm(defaultMessage));
        }

        if (titleEl) {
            titleEl.textContent = options && options.title ? options.title : "Confirmation";
        }
        if (messageEl) {
            messageEl.textContent = defaultMessage;
        }
        if (submitBtn) {
            submitBtn.textContent = options && options.confirmText ? options.confirmText : "Confirmer";
        }
        if (cancelBtn) {
            cancelBtn.textContent = options && options.cancelText ? options.cancelText : "Annuler";
        }

        var existingModal = KTModal.getInstance(modalEl);
        if (!existingModal) {
            return Promise.resolve(window.confirm(defaultMessage));
        }

        return new Promise(function (resolve) {
            closeCalendarPopovers();

            var resolved = false;
            var modal = existingModal;

            function cleanup() {
                if (submitBtn) submitBtn.removeEventListener('click', onConfirm);
                if (cancelBtn) cancelBtn.removeEventListener('click', onCancel);
                if (closeBtn) closeBtn.removeEventListener('click', onCancel);
                modalEl.removeEventListener('click', onBackdropClick);
            }

            function finish(value) {
                if (resolved) return;
                resolved = true;
                cleanup();
                resolve(value);
            }

            function onConfirm() {
                modal && modal.hide();
                finish(true);
            }

            function onCancel() {
                modal && modal.hide();
                finish(false);
            }

            function onBackdropClick(event) {
                if (event.target === modalEl) {
                    onCancel();
                }
            }

            if (submitBtn) submitBtn.addEventListener('click', onConfirm);
            if (cancelBtn) cancelBtn.addEventListener('click', onCancel);
            if (closeBtn) closeBtn.addEventListener('click', onCancel);
            modalEl.addEventListener('click', onBackdropClick);

            modal && modal.show();
        });
    }
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: "Aujourd'hui",
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour',
            list: 'Liste'
        },
        navLinks: true, // can click day/week names to navigate views
        editable: true,
        droppable: true,
        dayMaxEvents: true, // allow "more" link when too many events
        events: eventsUrl,
        
        eventContent: function (info) {
            var element = document.createElement('div');
            element.innerHTML = '<strong>' + info.event.title + '</strong><br/>' + 
                (info.event.extendedProps.phone ? info.event.extendedProps.phone : '');
            return { html: element.innerHTML };
        },

        // Planifier une demande: Click on day opens the new form.
        dateClick: function(arg) {
            if (newUrl) {
                // Could append date parameter if needed: newUrl + '?date=' + arg.dateStr
                window.location.href = newUrl;
            }
        },
        
        // Rescheduling: Drag and drop
        eventDrop: function (info) {
            showConfirmationModal({
                title: "Replanification",
                message: "Voulez-vous replanifier: " + info.event.title + " ?",
                confirmText: "Replanifier",
                cancelText: "Annuler"
            }).then(function (isConfirmed) {
                if (!isConfirmed) {
                    info.revert();
                    return;
                }

                var newDateStr = info.event.startStr;
                var eventId = info.event.id;
                var updateUrl = moveUrlTemplate.replace('__ID__', eventId);

                fetch(updateUrl, {
                    method: 'POST',
                    body: JSON.stringify({ newDate: newDateStr }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Event moved successfully');
                    } else {
                        alert("Erreur: " + data.message);
                        info.revert();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("Erreur serveur lors de la replanification.");
                    info.revert();
                });
            });
        }
    });

    calendar.render();

    var refreshBtn = document.getElementById('calendar_refresh_btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            calendar.refetchEvents();
            // Optionally reload stats dynamically
            location.reload();
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('kt_calendar_app');
    if (!calendarEl) return;

    var eventsUrl = calendarEl.dataset.eventsUrl;
    var newUrl = calendarEl.dataset.newUrl;
    
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
            // Could redirect to the new form with a pre-filled date if implemented later
            if (newUrl) {
                window.location.href = newUrl;
            }
        },
        
        // Rescheduling: Drag and drop
        eventDrop: function (info) {
            if (!confirm("Voulez-vous replanifier: " + info.event.title + " ?")) {
                info.revert();
                return;
            }

            var newDateStr = info.event.startStr;
            var eventId = info.event.id;

            fetch('/ramassage/' + eventId + '/calendar-move', {
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

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('deleteUserModal');
        if (!modal) {
            return;
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }
            var userId = trigger.getAttribute('data-user-id') || '';
            var userName = trigger.getAttribute('data-user-name') || '';
            var username = trigger.getAttribute('data-username') || '';

            var idInput = document.getElementById('delete_user_id');
            var label = document.getElementById('deleteUserModalLabel');
            var summary = document.getElementById('deleteUserSummary');

            if (idInput) {
                idInput.value = userId;
            }
            if (label) {
                label.textContent = 'Delete account';
            }
            if (summary) {
                summary.textContent = 'Delete ' + userName + ' (@' + username + ')? This cannot be undone. Complaints and related records for this user may also be removed.';
            }
        });
    });
})();

// The lifecycle form also supports individual selection without JavaScript.
document.addEventListener('change', function (event) {
	if (!event.target.matches('.contacthub-trash-select-all')) return;
	event.target.closest('form').querySelectorAll('input[name="ids[]"]').forEach(function (checkbox) { checkbox.checked = event.target.checked; });
});


// App-level JS for A.V.M TEX ERP
(() => {
  // Auto-focus username on login page when present.
  const user = document.querySelector('input[name="username"]');
  if (user) user.focus();

  const phone = document.querySelector('input[name="phone"]');
  if (phone && !document.querySelector('input[name="username"]')) phone.focus();

  // Delete customer modal: populate id and name from trigger button.
  const deleteModal = document.getElementById('deleteCustomerModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const button = event.relatedTarget;
      if (!button) return;

      const id = button.getAttribute('data-id') || '';
      const name = button.getAttribute('data-name') || '';

      const idInput = document.getElementById('deleteCustomerId');
      const nameEl = document.getElementById('deleteCustomerName');

      if (idInput) idInput.value = id;
      if (nameEl) nameEl.textContent = name;
    });
  }

  // Client-side validation feedback for customer forms.
  document.querySelectorAll('.avm-customer-form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });
})();


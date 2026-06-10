document.addEventListener("DOMContentLoaded", () => {
  const deleteButtons = Array.from(document.querySelectorAll('[data-bon-delete-open="true"]'));
  const deleteForm = document.getElementById("bon-livraison-delete-form");
  const deleteTitle = document.querySelector('[data-bon-delete-title="true"]');
  const deleteToken = document.querySelector('[data-bon-delete-token="true"]');

  if (!deleteForm) {
    return;
  }

  deleteButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const title = button.getAttribute("data-delete-title") || "";
      const action = button.getAttribute("data-delete-action") || "";
      const token = button.getAttribute("data-delete-token") || "";

      if (deleteTitle) {
        deleteTitle.textContent = title;
      }
      deleteForm.setAttribute("action", action);
      if (deleteToken) {
        deleteToken.value = token;
      }
    });
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const showToast = (message, isError = true) => {
    let container = document.getElementById("global-toasts");
    if (!container) {
      container = document.createElement("div");
      container.id = "global-toasts";
      container.className = "fixed flex flex-col gap-3";
      container.style.cssText = "top: 20px; right: 20px; z-index: 2147483647;";
      document.body.appendChild(container);
    }

    const wrapperStyle = isError
      ? "background-color: #fff5f8; border: 1px solid #f1416c; color: #f1416c;"
      : "background-color: #e8fff3; border: 1px solid #50cd89; color: #50cd89;";

    const toast = document.createElement("div");
    toast.className =
      "flex items-center gap-3 p-4 rounded-xl shadow-lg transition-opacity duration-300";
    toast.style.cssText = wrapperStyle;
    toast.setAttribute("role", "alert");
    toast.innerHTML = `
      <i class="ki-filled ${isError ? "ki-information-2" : "ki-check-circle"} text-xl"></i>
      <div class="font-medium flex-1"></div>
      <button type="button" class="ms-auto opacity-80 hover:opacity-100 transition-opacity">
        <i class="ki-filled ki-cross"></i>
      </button>
    `;
    toast.querySelector(".font-medium").textContent = message;
    toast.querySelector("button").addEventListener("click", () => toast.remove());
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = "0";
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  };

  document.querySelectorAll('[data-bon-retour-download="true"]').forEach((link) => {
    link.addEventListener("click", async (event) => {
      event.preventDefault();

      if (link.getAttribute("data-can-download") !== "1") {
        showToast("Document non disponible");
        return;
      }

      const url = link.getAttribute("href") || "";
      const filename = link.getAttribute("data-filename") || "bon-retour.pdf";

      try {
        const response = await fetch(url, { credentials: "same-origin" });
        const contentType = response.headers.get("content-type") || "";

        if (!response.ok || !contentType.includes("application/pdf")) {
          showToast("Document non disponible");
          return;
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = objectUrl;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(objectUrl);
      } catch {
        showToast("Document non disponible");
      }
    });
  });
});

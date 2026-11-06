(() => {
  const adminContext = document.body.dataset.authContext === "admin";
  const contextQuery = adminContext ? "?context=admin" : "";
  const loginUrl = adminContext ? "login.html?context=admin" : "login.html";
  fetch(`api/auth/me.php${contextQuery}`, { credentials: "same-origin" })
    .then(async (response) => {
      if (!response.ok) {
        window.location.replace(loginUrl);
        return;
      }
      const result = await response.json();
      if (!result.ok || !result.user) {
        window.location.replace(loginUrl);
        return;
      }

      const user = result.user;
      const fullName = [user.first_name, user.last_name].filter(Boolean).join(" ") || user.email;
      const firstName = user.first_name || user.email.split("@")[0];
      document.querySelectorAll("[data-sidebar-profile-name], [data-profile-name]").forEach((node) => { node.textContent = fullName; });
      document.querySelectorAll("[data-profile-email]").forEach((node) => { node.textContent = user.email; });
      document.querySelectorAll("[data-auth-user-name]").forEach((node) => { node.textContent = fullName; });
      document.querySelectorAll("[data-auth-user-email]").forEach((node) => { node.textContent = user.email; });
      document.querySelectorAll("[data-auth-first-name]").forEach((node) => { node.textContent = firstName; });
      const initials = fullName.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join("").toUpperCase();
      document.querySelectorAll("[data-profile-initials]").forEach((node) => { node.textContent = initials; });
      document.querySelectorAll("[data-auth-user-initials]").forEach((node) => { node.textContent = initials; });
    })
    .catch(() => window.location.replace(loginUrl));

  document.querySelectorAll("[data-auth-logout]").forEach((button) => {
    button.addEventListener("click", async () => {
      button.disabled = true;
      try {
        const csrfResponse = await fetch(`api/auth/csrf.php${contextQuery}`, { credentials: "same-origin" });
        const csrf = await csrfResponse.json();
        const response = await fetch(`api/auth/logout.php${contextQuery}`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf.csrf_token },
          body: JSON.stringify({ csrf_token: csrf.csrf_token })
        });
        if (!response.ok) throw new Error("Sign-out failed.");
        window.location.assign(loginUrl);
      } catch {
        button.disabled = false;
      }
    });
  });
})();
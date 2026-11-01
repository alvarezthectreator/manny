(() => {
  if (window.lucide) window.lucide.createIcons();

  document.querySelectorAll("[data-billing]").forEach((button) => {
    button.addEventListener("click", () => {
      const yearly = button.dataset.billing === "yearly";
      document.querySelectorAll("[data-billing]").forEach((option) => {
        const selected = option === button;
        option.classList.toggle("active", selected);
        option.setAttribute("aria-pressed", String(selected));
      });
      document.querySelectorAll("[data-monthly]").forEach((price) => {
        const monthly = Number(price.dataset.monthly);
        price.textContent = `$${yearly ? Math.round(monthly * 10) : monthly}`;
        price.nextElementSibling.textContent = yearly ? " / year" : " / month";
      });
    });
  });

  const form = document.querySelector("#login-form");
  if (!form) return;
  const signup = new URLSearchParams(window.location.search).get("mode") === "signup";
  const nameField = document.querySelector("#name-field");
  const nameInput = document.querySelector("#full-name");
  const passwordInput = document.querySelector("#password");
  const toggleLink = document.querySelector("#mode-link");
  document.querySelector("#login-title").textContent = signup ? "Create your account." : "Welcome back.";
  document.querySelector("#login-subtitle").textContent = signup ? "Set up your Cladvest demo workspace." : "Sign in to pick up where you left off.";
  document.querySelector("#submit-label").textContent = signup ? "Create account" : "Log in";
  document.querySelector("#mode-prompt").textContent = signup ? "Already have an account?" : "New to Cladvest?";
  toggleLink.textContent = signup ? "Log in" : "Create an account";
  toggleLink.href = signup ? "login.html" : "login.html?mode=signup";
  nameField.hidden = !signup;
  passwordInput.autocomplete = signup ? "new-password" : "current-password";
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    window.location.assign("dashboard.html");
  });
})();
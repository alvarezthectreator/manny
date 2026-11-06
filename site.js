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
  const search = new URLSearchParams(window.location.search);
  const adminContext = search.get("context") === "admin";
  const signup = !adminContext && search.get("mode") === "signup";
  document.title = signup ? "Create account | Cladvest" : "Sign in | Cladvest";
  const signupDetails = document.querySelector("#signup-details");
  const countrySelect = document.querySelector("#country-code");
  const confirmPasswordField = document.querySelector("#confirm-password-field");
  const confirmPasswordInput = document.querySelector("#confirm-password");
  const passwordInput = document.querySelector("#password");
  const toggleLink = document.querySelector("#mode-link");
  const messageBox = document.querySelector("#auth-message");
  const message = messageBox.querySelector("span");
  const submitButton = form.querySelector("button[type='submit']");
  document.querySelector("#login-title").textContent = signup ? "Create your account." : adminContext ? "Administrator sign in." : "Welcome back.";
  document.querySelector("#login-subtitle").textContent = signup ? "Set up your Cladvest demo workspace." : adminContext ? "Sign in with an authorized administrator account." : "Sign in to pick up where you left off.";
  document.querySelector("#submit-label").textContent = signup ? "Create account" : "Log in";
  document.querySelector("#mode-prompt").textContent = signup ? "Already have an account?" : "New to Cladvest?";
  toggleLink.textContent = signup ? "Log in" : "Create an account";
  toggleLink.href = signup ? "login.html" : "login.html?mode=signup";
  signupDetails.hidden = !signup;
  if (signup && countrySelect) {
    const countryCodes = "AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW".split(" ");
    const countryNames = typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "region" }) : null;
    const options = countryCodes.map((code) => ({ code, name: countryNames?.of(code) || code }))
      .sort((first, second) => first.name.localeCompare(second.name));
    countrySelect.append(...options.map(({ code, name }) => {
      const option = document.createElement("option");
      option.value = code;
      option.textContent = `${name} (${code})`;
      return option;
    }));
  }
  confirmPasswordField.hidden = !signup;
  document.querySelector("#signup-prompt").hidden = adminContext;
  document.querySelector("#admin-login-prompt").hidden = !adminContext;
  form.querySelectorAll("#signup-details input").forEach((input) => {
    input.required = signup && ["first_name", "last_name"].includes(input.name);
  });
  confirmPasswordInput.required = signup;
  passwordInput.autocomplete = signup ? "new-password" : "current-password";
  passwordInput.minLength = signup ? 12 : 1;

  const contextQuery = adminContext ? "?context=admin" : "";
  fetch(`api/auth/me.php${contextQuery}`, { credentials: "same-origin" }).then((response) => {
    if (response.ok) window.location.replace(adminContext ? "admin.html" : "dashboard.html");
  }).catch(() => {});

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    message.textContent = "";
    messageBox.hidden = true;
    submitButton.disabled = true;
    const formData = new FormData(form);
    if (signup && formData.get("password") !== formData.get("confirm_password")) {
      message.textContent = "Passwords do not match.";
      messageBox.hidden = false;
      submitButton.disabled = false;
      confirmPasswordInput.focus();
      return;
    }
    const request = async () => {
      const csrfResponse = await fetch(`api/auth/csrf.php${contextQuery}`, { credentials: "same-origin" });
      const csrf = await csrfResponse.json();
      if (!csrfResponse.ok || !csrf.csrf_token) throw new Error("Could not start a secure session. Check that MAMP is running.");
      const authPath = signup ? "api/auth/register.php" : `api/auth/login.php${contextQuery}`;
      const response = await fetch(authPath, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf.csrf_token },
        body: JSON.stringify({
          first_name: formData.get("first_name"),
          last_name: formData.get("last_name"),
          phone_country_code: formData.get("phone_country_code"),
          phone_number: formData.get("phone_number"),
          region: formData.get("region"),
          email: formData.get("email"),
          password: formData.get("password"),
          csrf_token: csrf.csrf_token
        })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Authentication failed.");
      window.location.assign(result.redirect || "dashboard.html");
    };
    request().catch((error) => {
      message.textContent = error.message;
      messageBox.hidden = false;
    })
      .finally(() => { submitButton.disabled = false; });
  });
})();
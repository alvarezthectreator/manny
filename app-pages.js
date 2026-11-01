(() => {
  const navItems = [
    ["Dashboard", "index.html", '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
    ["Opportunities", "opportunities.html", '<path d="M4 17 10 11l4 4 6-8"/><path d="M14 7h6v6"/>'],
    ["Portfolio", "portfolio.html", '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 10h18M8 5V3h8v2"/>'],
    ["Add funds", "add-funds.html", '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'],
    ["Withdraw", "withdraw.html", '<path d="M12 4v13m-5-5 5 5 5-5M5 21h14"/>'],
    ["Activity", "activity.html", '<path d="M4 7h13l-3-3m3 3-3 3M20 17H7l3 3m-3-3 3-3"/>'],
    ["Analysis", "analysis.html", '<path d="M4 20V4m0 16h16M7 15l4-5 3 2 5-7"/>'],
    ["Chat", "chat.html", '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>'],
    ["Profile", "profile.html", '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>']
  ];
  const pageName = document.body.dataset.page || "Dashboard";
  const app = document.querySelector("#app");
  const pageContent = app?.querySelector(".page-body");
  if (!app || !pageContent) return;

  const navMarkup = navItems.map(([label, href, icon]) =>
    `<a href="${href}"${label.toLowerCase() === pageName.toLowerCase() ? ' aria-current="page"' : ""}><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">${icon}</svg>${label}</a>`
  ).join("");
  const sidebar = document.createElement("aside");
  sidebar.className = "sidebar";
  sidebar.innerHTML = `<a class="brand" href="index.html"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="#fff"><path d="M12 3.2l3 3-3 3-3-3z"/><path d="M6.2 9l3 3-3 3-3-3z"/><path d="M17.8 9l3 3-3 3-3-3z"/><path d="M12 14.8l3 3-3 3-3-3z"/></svg></span><span class="brand-name">Coinza</span></a><span class="demo-badge">Demo account</span><p class="nav-label">Invest</p><nav class="side-nav">${navMarkup}</nav><div class="sidebar-foot"><a class="profile-link" href="profile.html"><span class="avatar">OH</span><span class="profile-meta"><strong>Oripio Hossain</strong><small>Demo profile</small></span></a></div>`;

  const shell = document.createElement("div");
  shell.className = "app-shell";
  const mainColumn = document.createElement("div");
  mainColumn.className = "main-column";
  const topbar = document.createElement("header");
  topbar.className = "topbar";
  topbar.innerHTML = `<div class="topbar-title"><small>Coinza / Demo workspace</small><strong>${pageName}</strong></div><div class="topbar-actions"><span class="demo-badge">No real funds</span><a class="icon-button" href="chat.html" aria-label="Open chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/></svg></a></div>`;
  const mobileNav = document.createElement("nav");
  mobileNav.className = "mobile-nav";
  mobileNav.setAttribute("aria-label", "Main navigation");
  mobileNav.innerHTML = navMarkup;
  const main = document.createElement("main");
  main.className = "page-content";
  main.append(pageContent);
  mainColumn.append(topbar, mobileNav, main);
  shell.append(sidebar, mainColumn);
  app.replaceChildren(shell);

  const storageKey = "coinza-demo-v1";
  const startingState = {
    cash: 12450,
    invested: 12000,
    transactions: [
      { type: "Deposit", detail: "Bank transfer", date: "Oct 30, 2026", amount: 1250, status: "Demo completed" },
      { type: "Investment", detail: "Tesla (TSLA)", date: "Oct 28, 2026", amount: -500, status: "Demo completed" },
      { type: "Deposit", detail: "Crypto deposit", date: "Oct 24, 2026", amount: 300, status: "Demo completed" }
    ]
  };

  function readState() {
    try {
      const saved = JSON.parse(localStorage.getItem(storageKey) || "null");
      return saved && Number.isFinite(saved.cash) && Number.isFinite(saved.invested) && Array.isArray(saved.transactions) ? saved : structuredClone(startingState);
    } catch {
      return structuredClone(startingState);
    }
  }

  function saveState(state) {
    try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* Storage is optional in this demo. */ }
  }

  function money(value) {
    return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(value);
  }

  function showToast(message) {
    document.querySelector(".toast")?.remove();
    const toast = document.createElement("div");
    toast.className = "toast";
    toast.setAttribute("role", "status");
    toast.textContent = message;
    document.body.append(toast);
    window.setTimeout(() => toast.remove(), 4800);
  }

  function renderState() {
    const state = readState();
    document.querySelectorAll("[data-demo-cash]").forEach((node) => { node.textContent = money(state.cash); });
    document.querySelectorAll("[data-demo-total]").forEach((node) => { node.textContent = money(state.cash + state.invested); });
    const balance = document.querySelector("[data-available-balance]");
    if (balance) balance.textContent = money(state.cash);
    const transactionBody = document.querySelector("[data-transaction-list]");
    if (transactionBody) {
      transactionBody.innerHTML = state.transactions.map((transaction) => `<tr data-filter-item><td>${transaction.date}</td><td>${transaction.type}</td><td>${transaction.detail}</td><td>${money(transaction.amount)}</td><td><span class="status status-demo">${transaction.status}</span></td></tr>`).join("");
    }
  }

  renderState();

  document.querySelectorAll("form[data-demo-form='deposit']").forEach((form) => {
    const updateMethod = () => {
      const selected = form.querySelector("[name='method']:checked");
      const summary = document.querySelector("[data-selected-method]");
      if (selected && summary) summary.textContent = selected.value;
    };
    form.addEventListener("change", updateMethod);
  });

  document.querySelectorAll("[data-demo-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const amountInput = form.querySelector("[name='amount']");
      const amount = Number(amountInput?.value);
      if (!Number.isFinite(amount) || amount <= 0) {
        showToast("Enter an amount greater than $0 to continue.");
        amountInput?.focus();
        return;
      }

      const state = readState();
      const action = form.dataset.demoForm;
      if (action === "deposit" && amount > 100000) {
        showToast("The demo deposit limit is $100,000 per transaction.");
        return;
      }
      if (action === "withdraw" && amount > state.cash) {
        showToast("That amount is above your available demo balance.");
        return;
      }

      const method = form.querySelector("[name='method']:checked")?.value || form.querySelector("[name='method']")?.value || "Demo method";
      const type = action === "withdraw" ? "Withdrawal" : "Deposit";
      state.cash += action === "withdraw" ? -amount : amount;
      state.transactions.unshift({ type, detail: method, date: new Intl.DateTimeFormat("en-US", { month: "short", day: "2-digit", year: "numeric" }).format(new Date()), amount: action === "withdraw" ? -amount : amount, status: "Demo completed" });
      saveState(state);
      renderState();
      form.reset();
      showToast(`${type} simulated for ${money(amount)}. No money was sent or received.`);
    });
  });

  document.querySelectorAll("[data-chat-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const input = form.querySelector("[name='message']");
      const message = input?.value.trim();
      if (!message) return;
      const messages = document.querySelector("[data-chat-messages]");
      const bubble = document.createElement("div");
      bubble.className = "chat-bubble mine";
      bubble.textContent = message;
      messages?.append(bubble);
      input.value = "";
      const reply = document.createElement("div");
      reply.className = "chat-bubble";
      reply.textContent = "This is a demo support chat. A live support agent is not connected.";
      messages?.append(reply);
      messages?.scrollTo({ top: messages.scrollHeight, behavior: "smooth" });
    });
  });

  document.querySelectorAll("[data-filter-input]").forEach((input) => {
    input.addEventListener("input", () => {
      const query = input.value.toLowerCase();
      document.querySelectorAll("[data-filter-item]").forEach((item) => {
        item.hidden = !item.textContent.toLowerCase().includes(query);
      });
    });
  });
})();
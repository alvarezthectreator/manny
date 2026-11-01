(() => {
  const navGroups = [
    ["OVERVIEW", [
      ["Dashboard", "dashboard.html", '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
      ["Opportunities", "opportunities.html", '<path d="M4 17 10 11l4 4 6-8"/><path d="M14 7h6v6"/>'],
      ["Investment details", "investment-detail.html", '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6m-3-3h6"/>'],
      ["Analysis", "analysis.html", '<path d="M4 20V4m0 16h16M7 15l4-5 3 2 5-7"/>']
    ]],
    ["PORTFOLIO", [
      ["Portfolio", "portfolio.html", '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 10h18M8 5V3h8v2"/>'],
      ["Goals", "goals.html", '<path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="8"/>'],
      ["Loans", "loans.html", '<path d="M12 3v18m5-14H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>']
    ]],
    ["MONEY", [
      ["Add funds", "add-funds.html", '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'],
      ["Withdraw", "withdraw.html", '<path d="M12 4v13m-5-5 5 5 5-5M5 21h14"/>'],
      ["Activity", "activity.html", '<path d="M4 7h13l-3-3m3 3-3 3M20 17H7l3 3m-3-3 3-3"/>']
    ]],
    ["SUPPORT", [
      ["Chat", "chat.html", '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>'],
      ["Profile", "profile.html", '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>']
    ]]
  ];

  function wireSidebar(root, sidebar, toggle, backdrop) {
    if (!root || !sidebar || !toggle || !backdrop) return;
    const mobileView = window.matchMedia("(max-width: 991.98px)");
    const close = (restoreFocus = false) => {
      root.classList.remove("nav-open");
      document.body.classList.remove("drawer-open");
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Open navigation");
      if (restoreFocus) toggle.focus();
    };
    const open = () => {
      root.classList.add("nav-open");
      document.body.classList.add("drawer-open");
      toggle.setAttribute("aria-expanded", "true");
      toggle.setAttribute("aria-label", "Close navigation");
      sidebar.querySelector("a")?.focus();
    };

    toggle.addEventListener("click", () => {
      root.classList.contains("nav-open") ? close() : open();
    });
    backdrop.addEventListener("click", () => close(true));
    sidebar.addEventListener("click", (event) => {
      if (mobileView.matches && event.target.closest("a")) close();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && root.classList.contains("nav-open")) close(true);
    });
    mobileView.addEventListener("change", (event) => {
      if (!event.matches) close();
    });
  }

  const pageName = document.body.dataset.page || "Dashboard";
  const app = document.querySelector("#app");
  const pageContent = app?.querySelector(".page-body");
  if (!app || !pageContent) {
    const dashboard = document.querySelector(".cz-root");
    wireSidebar(dashboard, dashboard?.querySelector(".cz-sidebar"), dashboard?.querySelector(".cz-menu-toggle"), dashboard?.querySelector(".cz-sidebar-backdrop"));
    return;
  }

  const navMarkup = navGroups.map(([group, items]) =>
    `<section class="nav-group"><p class="nav-label">${group}</p><div class="side-nav">${items.map(([label, href, icon]) => `<a href="${href}"${label.toLowerCase() === pageName.toLowerCase() ? ' aria-current="page"' : ""}><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">${icon}</svg>${label}</a>`).join("")}</div></section>`
  ).join("");
  const sidebar = document.createElement("aside");
  sidebar.className = "sidebar";
  sidebar.id = "primary-sidebar";
  sidebar.setAttribute("aria-label", "Primary navigation");
  sidebar.innerHTML = `<a class="brand" href="dashboard.html"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="#2fbf71"><path d="M12 3.2l3 3-3 3-3-3z"/><path d="M6.2 9l3 3-3 3-3-3z"/><path d="M17.8 9l3 3-3 3-3-3z"/><path d="M12 14.8l3 3-3 3-3-3z"/></svg></span><span class="brand-name">Cladvest</span></a><nav class="nav-sections" aria-label="Primary navigation">${navMarkup}</nav><div class="sidebar-foot"><a class="profile-link" href="profile.html"><span class="avatar">OH</span><span class="profile-meta"><strong>Oripio Hossain</strong><small>Account owner</small></span></a></div>`;

  const shell = document.createElement("div");
  shell.className = "app-shell";
  const mainColumn = document.createElement("div");
  mainColumn.className = "main-column";
  const topbar = document.createElement("header");
  topbar.className = "topbar";
  topbar.innerHTML = `<div class="topbar-leading"><button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="primary-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><div class="topbar-title"><small>Cladvest / Workspace</small><strong>${pageName}</strong></div></div><div class="topbar-actions"><a class="icon-button" href="chat.html" aria-label="Open chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/></svg></a></div>`;
  const backdrop = document.createElement("button");
  backdrop.className = "sidebar-backdrop";
  backdrop.type = "button";
  backdrop.setAttribute("aria-label", "Close navigation");
  const main = document.createElement("main");
  main.className = "page-content";
  main.append(pageContent);
  mainColumn.append(topbar, main);
  shell.append(sidebar, backdrop, mainColumn);
  app.replaceChildren(shell);
  wireSidebar(shell, sidebar, topbar.querySelector(".menu-toggle"), backdrop);

  const storageKey = "coinza-demo-v1";
  const startingState = {
    cash: 12450,
    invested: 12000,
    goals: [
      { id: "goal-sample", name: "Long-term portfolio", target: 50000, current: 18450, dueDate: "2030-12-31" }
    ],
    recurringPlans: [
      { id: "plan-sample", asset: "Tesla (TSLA)", amount: 50, frequency: "Monthly", nextRun: "Nov 15, 2026", active: true }
    ],
    loanRequests: [],
    loanReminder: true,
    transactions: [
      { type: "Deposit", detail: "Bank transfer", date: "Oct 30, 2026", amount: 1250, status: "Recorded" },
      { type: "Investment", detail: "Tesla (TSLA)", date: "Oct 28, 2026", amount: -500, status: "Recorded" },
      { type: "Deposit", detail: "Crypto deposit", date: "Oct 24, 2026", amount: 300, status: "Recorded" }
    ]
  };

  function readState() {
    try {
      const saved = JSON.parse(localStorage.getItem(storageKey) || "null");
      if (!saved || !Number.isFinite(saved.cash) || !Number.isFinite(saved.invested) || !Array.isArray(saved.transactions)) return structuredClone(startingState);
      return {
        ...structuredClone(startingState),
        ...saved,
        goals: Array.isArray(saved.goals) ? saved.goals : structuredClone(startingState.goals),
        recurringPlans: Array.isArray(saved.recurringPlans) ? saved.recurringPlans : structuredClone(startingState.recurringPlans),
        loanRequests: Array.isArray(saved.loanRequests) ? saved.loanRequests : [],
        loanReminder: typeof saved.loanReminder === "boolean" ? saved.loanReminder : true
      };
    } catch {
      return structuredClone(startingState);
    }
  }

  function saveState(state) {
    try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* Local storage is optional. */ }
  }

  function money(value) {
    return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(value);
  }

  function renderGoals(state) {
    const goalList = document.querySelector("[data-goal-list]");
    if (!goalList) return;
    goalList.replaceChildren();
    if (!state.goals.length) {
      const empty = document.createElement("p");
      empty.className = "empty-state";
      empty.textContent = "No goals yet. Create one to start tracking progress.";
      goalList.append(empty);
      return;
    }
    for (const goal of state.goals) {
      const card = document.createElement("article");
      card.className = "goal-card";
      const top = document.createElement("div");
      top.className = "d-flex align-items-start justify-content-between gap-3";
      const name = document.createElement("h3");
      name.className = "list-title";
      name.textContent = goal.name;
      const due = document.createElement("span");
      due.className = "pill";
      due.textContent = goal.dueDate ? `Target ${goal.dueDate}` : "No target date";
      top.append(name, due);
      const values = document.createElement("div");
      values.className = "goal-values";
      values.innerHTML = `<span>${money(goal.current)} saved</span><strong>${money(goal.target)}</strong>`;
      const progress = document.createElement("progress");
      progress.className = "goal-progress";
      progress.max = Math.max(1, goal.target);
      progress.value = Math.min(goal.current, goal.target);
      progress.setAttribute("aria-label", `${goal.name} progress`);
      const percent = document.createElement("span");
      percent.className = "hint";
      percent.textContent = `${Math.min(100, Math.round(goal.current / Math.max(1, goal.target) * 100))}% of goal`;
      card.append(top, values, progress, percent);
      goalList.append(card);
    }
  }

  function renderRecurringPlans(state) {
    const planList = document.querySelector("[data-recurring-list]");
    if (!planList) return;
    planList.replaceChildren();
    if (!state.recurringPlans.length) {
      const empty = document.createElement("p");
      empty.className = "empty-state";
      empty.textContent = "No recurring plans. Add a plan to preview a schedule.";
      planList.append(empty);
      return;
    }
    for (const plan of state.recurringPlans) {
      const row = document.createElement("article");
      row.className = "list-row recurring-row";
      const description = document.createElement("div");
      description.innerHTML = `<div class="list-title"></div><div class="list-subtitle"></div>`;
      description.querySelector(".list-title").textContent = `${plan.asset} · ${money(plan.amount)} ${plan.frequency.toLowerCase()}`;
      description.querySelector(".list-subtitle").textContent = `Next scheduled date: ${plan.nextRun} · ${plan.active ? "Active" : "Paused"}`;
      const actions = document.createElement("div");
      actions.className = "button-row";
      const toggle = document.createElement("button");
      toggle.className = "button";
      toggle.type = "button";
      toggle.dataset.planAction = "toggle";
      toggle.dataset.planId = plan.id;
      toggle.textContent = plan.active ? "Pause" : "Resume";
      const remove = document.createElement("button");
      remove.className = "button button-quiet";
      remove.type = "button";
      remove.dataset.planAction = "remove";
      remove.dataset.planId = plan.id;
      remove.textContent = "Remove";
      actions.append(toggle, remove);
      row.append(description, actions);
      planList.append(row);
    }
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
    document.querySelectorAll("[data-account-cash]").forEach((node) => { node.textContent = money(state.cash); });
    document.querySelectorAll("[data-account-total]").forEach((node) => { node.textContent = money(state.cash + state.invested); });
    const balance = document.querySelector("[data-available-balance]");
    if (balance) balance.textContent = money(state.cash);
    const transactionBody = document.querySelector("[data-transaction-list]");
    if (transactionBody) {
      transactionBody.innerHTML = state.transactions.map((transaction) => {
        const status = String(transaction.status || "Recorded").toLowerCase().endsWith("completed") ? "Recorded" : transaction.status || "Recorded";
        return `<tr data-filter-item data-transaction-type="${transaction.type}"><td>${transaction.date}</td><td>${transaction.type}</td><td>${transaction.detail}</td><td>${money(transaction.amount)}</td><td><span class="status status-simulated">${status}</span></td></tr>`;
      }).join("");
    }
    renderGoals(state);
    renderRecurringPlans(state);
    renderLoanCenter(state);
  }

  function renderLoanCenter(state) {
    const requestList = document.querySelector("[data-loan-requests]");
    if (requestList) {
      requestList.replaceChildren();
      if (!state.loanRequests.length) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = "No requests submitted yet.";
        requestList.append(empty);
      }
      for (const request of state.loanRequests) {
        const row = document.createElement("div");
        row.className = "list-row";
        const details = document.createElement("div");
        details.innerHTML = '<div class="list-title"></div><div class="list-subtitle"></div>';
        details.querySelector(".list-title").textContent = `${money(request.amount)} · ${request.months} months`;
        details.querySelector(".list-subtitle").textContent = `Submitted ${request.date} · ${money(request.payment)}/mo estimate`;
        const status = document.createElement("span");
        status.className = "status status-simulated";
        status.textContent = "Submitted";
        row.append(details, status);
        requestList.append(row);
      }
    }
    const reminder = document.querySelector("[data-loan-reminder]");
    if (reminder) reminder.checked = state.loanReminder;
  }

  renderState();

  const goalForm = document.querySelector("[data-goal-form]");
  goalForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!goalForm.reportValidity()) return;
    const state = readState();
    state.goals.unshift({
      id: `goal-${Date.now()}`,
      name: goalForm.querySelector("[name='goal-name']").value.trim(),
      target: Number(goalForm.querySelector("[name='goal-target']").value),
      current: 0,
      dueDate: goalForm.querySelector("[name='goal-date']").value
    });
    saveState(state);
    renderState();
    goalForm.reset();
    showToast("Goal saved. No money was invested.");
  });

  const recurringForm = document.querySelector("[data-recurring-form]");
  recurringForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!recurringForm.reportValidity()) return;
    const state = readState();
    const frequency = recurringForm.querySelector("[name='frequency']").value;
    const nextDate = new Date();
    nextDate.setDate(nextDate.getDate() + (frequency === "Weekly" ? 7 : frequency === "Biweekly" ? 14 : 30));
    state.recurringPlans.unshift({
      id: `plan-${Date.now()}`,
      asset: recurringForm.querySelector("[name='asset']").value,
      amount: Number(recurringForm.querySelector("[name='plan-amount']").value),
      frequency,
      nextRun: nextDate.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }),
      active: true
    });
    saveState(state);
    renderState();
    recurringForm.reset();
    showToast("Schedule saved. No investment will execute automatically.");
  });

  document.querySelector("[data-recurring-list]")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-plan-action]");
    if (!button) return;
    const state = readState();
    const plan = state.recurringPlans.find((item) => item.id === button.dataset.planId);
    if (!plan) return;
    if (button.dataset.planAction === "remove") {
      state.recurringPlans = state.recurringPlans.filter((item) => item.id !== button.dataset.planId);
    } else {
      plan.active = !plan.active;
    }
    saveState(state);
    renderState();
  });

  document.querySelectorAll("form[data-funds-action='deposit']").forEach((form) => {
    const updateMethod = () => {
      const selected = form.querySelector("[name='method']:checked");
      const summary = document.querySelector("[data-selected-method]");
      if (selected && summary) summary.textContent = selected.value;
    };
    form.addEventListener("change", updateMethod);
  });

  document.querySelectorAll("[data-funds-action]").forEach((form) => {
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
      const action = form.dataset.fundsAction;
      if (action === "deposit" && amount > 100000) {
        showToast("The maximum deposit entry is $100,000 per transaction.");
        return;
      }
      if (action === "withdraw" && amount > state.cash) {
        showToast("That amount is above your available balance.");
        return;
      }

      const method = form.querySelector("[name='method']:checked")?.value || form.querySelector("[name='method']")?.value || "Payment method";
      const type = action === "withdraw" ? "Withdrawal" : "Deposit";
      state.cash += action === "withdraw" ? -amount : amount;
      state.transactions.unshift({ type, detail: method, date: new Intl.DateTimeFormat("en-US", { month: "short", day: "2-digit", year: "numeric" }).format(new Date()), amount: action === "withdraw" ? -amount : amount, status: "Recorded" });
      saveState(state);
      renderState();
      form.reset();
      showToast(`${type} simulated for ${money(amount)}. No money was sent or received.`);
    });
  });

  const loanForm = document.querySelector("[data-loan-form]");
  if (loanForm) {
    const amountInput = loanForm.querySelector("[name='loan-amount']");
    const termInput = loanForm.querySelector("[name='loan-term']");
    const apr = 8.9;
    const loanMoney = (value) => new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(value);
    const updateEstimate = () => {
      const principal = Number(amountInput.value);
      const months = Number(termInput.value);
      const monthlyRate = apr / 100 / 12;
      const payment = principal > 0 && months > 0
        ? principal * monthlyRate / (1 - Math.pow(1 + monthlyRate, -months))
        : 0;
      loanForm.querySelector("[data-loan-payment]").textContent = loanMoney(payment);
      loanForm.querySelector("[data-loan-total]").textContent = loanMoney(payment * months);
      loanForm.querySelector("[data-loan-interest]").textContent = loanMoney(Math.max(0, payment * months - principal));
      const schedule = document.querySelector("[data-loan-schedule]");
      if (schedule) {
        schedule.replaceChildren();
        for (let installment = 1; installment <= Math.min(3, months); installment += 1) {
          const date = new Date();
          date.setMonth(date.getMonth() + installment);
          const row = document.createElement("tr");
          for (const value of [String(installment), date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }), loanMoney(payment)]) {
            const cell = document.createElement("td");
            cell.textContent = value;
            row.append(cell);
          }
          const status = document.createElement("td");
          status.textContent = "Illustrative only";
          row.append(status);
          schedule.append(row);
        }
      }
    };

    amountInput.addEventListener("input", updateEstimate);
    termInput.addEventListener("change", updateEstimate);
    loanForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!loanForm.reportValidity()) return;
      const state = readState();
      const principal = Number(amountInput.value);
      const months = Number(termInput.value);
      const monthlyRate = apr / 100 / 12;
      const payment = principal * monthlyRate / (1 - Math.pow(1 + monthlyRate, -months));
      state.loanRequests.unshift({
        amount: principal,
        months,
        payment,
        date: new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric", year: "numeric" }).format(new Date()),
        status: "Submitted"
      });
      saveState(state);
      renderState();
      showToast("Request recorded. No credit check was run and no funds were issued.");
    });
    updateEstimate();
  }

  document.querySelector("[data-loan-reminder]")?.addEventListener("change", (event) => {
    const state = readState();
    state.loanReminder = event.currentTarget.checked;
    saveState(state);
    showToast("Reminder preference saved on this device.");
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
      reply.textContent = "This is an automated support preview. A live support agent is not connected.";
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

  const activitySearch = document.querySelector("[data-activity-search]");
  const activityTypeFilter = document.querySelector("[data-activity-type-filter]");
  const filterActivity = () => {
    const query = activitySearch?.value.toLowerCase() || "";
    const type = activityTypeFilter?.value || "";
    document.querySelectorAll("[data-transaction-list] [data-filter-item]").forEach((row) => {
      row.hidden = (type && row.dataset.transactionType !== type) || !row.textContent.toLowerCase().includes(query);
    });
  };
  activitySearch?.addEventListener("input", filterActivity);
  activityTypeFilter?.addEventListener("change", filterActivity);

  document.querySelector("[data-download-statement]")?.addEventListener("click", () => {
    const visibleRows = [...document.querySelectorAll("[data-transaction-list] tr:not([hidden])")];
    if (!visibleRows.length) {
      showToast("There are no visible transactions to export.");
      return;
    }
    const escapeCsv = (value) => `"${String(value).replaceAll('"', '""')}"`;
    const csvRows = [["Date", "Type", "Method / detail", "Amount (USD)", "Status"], ...visibleRows.map((row) => [...row.cells].map((cell) => cell.textContent.trim()))];
    const csv = csvRows.map((row) => row.map(escapeCsv).join(",")).join("\r\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `cladvest-statement-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    showToast("Activity statement exported as CSV.");
  });
})();
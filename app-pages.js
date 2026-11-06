(() => {
  const navGroups = [
    ["OVERVIEW", [
      ["Dashboard", "dashboard.html", '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
      ["Opportunities", "opportunities.html", '<path d="M4 17 10 11l4 4 6-8"/><path d="M14 7h6v6"/>'],
      ["Investment details", "investment-detail.html", '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6m-3-3h6"/>'],
      ["Analysis", "analysis.html", '<path d="M4 20V4m0 16h16M7 15l4-5 3 2 5-7"/>']
    ]],
    ["ACCOUNT", [
      ["Transactions", "activity.html", '<path d="M4 7h13l-3-3m3 3-3 3M20 17H7l3 3m-3-3 3-3"/>'],
      ["Goals", "goals.html", '<path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="8"/>'],
      ["Loans", "loans.html", '<path d="M12 3v18m5-14H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>']
    ]],
    ["MONEY", [
      ["Add funds", "add-funds.html", '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'],
      ["Withdraw", "withdraw.html", '<path d="M12 4v13m-5-5 5 5 5-5M5 21h14"/>']
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
  sidebar.innerHTML = `<a class="brand" href="dashboard.html"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="#2fbf71"><path d="M12 3.2l3 3-3 3-3-3z"/><path d="M6.2 9l3 3-3 3-3-3z"/><path d="M17.8 9l3 3-3 3-3-3z"/><path d="M12 14.8l3 3-3 3-3-3z"/></svg></span><span class="brand-name">Cladvest</span></a><nav class="nav-sections" aria-label="Primary navigation">${navMarkup}</nav><div class="sidebar-foot"><a class="profile-link" href="profile.html"><span class="avatar" data-profile-initials>OH</span><span class="profile-meta"><strong data-sidebar-profile-name>Oripio Hossain</strong><small>Account owner</small></span></a></div>`;

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

  function money(value) {
    return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(value);
  }

  async function renderGoals() {
    const goalList = document.querySelector("[data-goal-list]");
    if (!goalList || !window.cladvestApi) return;
    goalList.replaceChildren();
    try {
      const { goals } = await window.cladvestApi.get("api/goals/index.php");
      if (!goals.length) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = "No goals yet. Create one to start tracking progress.";
        goalList.append(empty);
        return;
      }
      for (const goal of goals) {
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
    } catch (error) {
      const empty = document.createElement("p");
      empty.className = "notice";
      empty.textContent = error.message;
      goalList.append(empty);
    }
  }

  async function renderRecurringPlans() {
    const planList = document.querySelector("[data-recurring-list], [data-investment-schedules]");
    if (!planList || !window.cladvestApi) return;
    planList.replaceChildren();
    try {
      const { plans } = await window.cladvestApi.get("api/recurring-plans/index.php");
      if (!plans.length) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = planList.hasAttribute("data-investment-schedules")
          ? "No investment schedules are on."
          : "No recurring plans. Add a plan to preview a schedule.";
        planList.append(empty);
        return;
      }
      for (const plan of plans) {
        const row = document.createElement("article");
        row.className = "list-row recurring-row";
        const description = document.createElement("div");
        description.innerHTML = '<div class="list-title"></div><div class="list-subtitle"></div>';
        description.querySelector(".list-title").textContent = `${plan.asset} · ${money(plan.amount)} ${plan.frequency.toLowerCase()}`;
        const ended = plan.status === "completed";
        const dateLabel = ended ? "Ended" : "Next scheduled date";
        const planState = ended ? "Ended" : plan.active ? "Active" : "Paused";
        description.querySelector(".list-subtitle").textContent = `${dateLabel}: ${new Date(plan.nextRun).toLocaleDateString()} · ${planState}`;
        const actions = document.createElement("div");
        actions.className = "button-row";
        if (ended) {
          const status = document.createElement("span");
          status.className = "status status-simulated";
          status.textContent = "Ended";
          actions.append(status);
        } else {
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
          remove.textContent = planList.hasAttribute("data-investment-schedules") ? "Stop" : "Remove";
          actions.append(toggle, remove);
        }
        row.append(description, actions);
        planList.append(row);
      }
    } catch (error) {
      const empty = document.createElement("p");
      empty.className = "notice";
      empty.textContent = error.message;
      planList.append(empty);
    }
  }

  function showToast(message) {
    document.querySelector(".app-toast")?.remove();
    const toast = document.createElement("div");
    toast.className = "app-toast";
    toast.setAttribute("role", "status");
    toast.textContent = message;
    document.body.append(toast);
    window.setTimeout(() => toast.remove(), 4800);
  }

  async function renderAccountSummary() {
    if (!window.cladvestApi || !document.querySelector("[data-account-total], [data-account-cash], [data-available-balance]")) return;
    try {
      const { summary } = await window.cladvestApi.get("api/accounts/index.php");
      document.querySelectorAll("[data-account-cash], [data-available-balance]").forEach((node) => { node.textContent = money(summary.cash_balance); });
      document.querySelectorAll("[data-account-total]").forEach((node) => { node.textContent = money(summary.total_balance); });
      document.querySelectorAll("[data-invested-value]").forEach((node) => { node.textContent = money(summary.invested_value); });
    } catch (error) {
      showToast(error.message);
    }
  }

  async function renderTransactions() {
    const transactionBody = document.querySelector("[data-transaction-list]");
    if (!transactionBody || !window.cladvestApi) return;
    try {
      const { transactions } = await window.cladvestApi.get("api/transactions/index.php?limit=100");
      transactionBody.replaceChildren();
      if (!transactions.length) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = 6;
        cell.className = "empty-state";
        cell.textContent = "No account activity yet.";
        row.append(cell);
        transactionBody.append(row);
        return;
      }
      for (const transaction of transactions) {
        const row = document.createElement("tr");
        row.dataset.filterItem = "";
        row.dataset.transactionType = transaction.type;
        const date = new Date(`${transaction.date.slice(0, 10)}T00:00:00`);
        const values = [date.toLocaleDateString(), transaction.type, transaction.detail, money(transaction.amount), transaction.status];
        for (const value of values) {
          const cell = document.createElement("td");
          cell.textContent = value;
          row.append(cell);
        }
        const receiptCell = document.createElement("td");
        if (transaction.has_receipt) {
          const receiptButton = document.createElement("button");
          receiptButton.type = "button";
          receiptButton.className = "button button-quiet receipt-button";
          receiptButton.textContent = "View receipt";
          receiptButton.addEventListener("click", () => {
            const dialog = document.querySelector("[data-transaction-receipt]");
            if (!dialog) return;
            const submitted = new Date(`${transaction.submitted_at.replace(" ", "T")}Z`);
            const receiptFields = {
              "[data-receipt-number]": transaction.receipt_number,
              "[data-receipt-date]": date.toLocaleDateString(),
              "[data-receipt-type]": transaction.type,
              "[data-receipt-detail]": transaction.detail,
              "[data-receipt-amount]": money(transaction.amount),
              "[data-receipt-status]": transaction.status,
              "[data-receipt-submitted]": submitted.toLocaleString()
            };
            Object.entries(receiptFields).forEach(([selector, value]) => {
              dialog.querySelector(selector).textContent = value;
            });
            dialog.showModal();
          });
          receiptCell.append(receiptButton);
        } else {
          receiptCell.textContent = "—";
        }
        row.append(receiptCell);
        transactionBody.append(row);
      }
    } catch (error) {
      transactionBody.replaceChildren();
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 6;
      cell.className = "empty-state";
      cell.textContent = error.message;
      row.append(cell);
      transactionBody.append(row);
    }
  }

  function renderState() {
    renderAccountSummary();
    renderTransactions();
    renderGoals();
    renderRecurringPlans();
    renderLoanCenter();
  }

  async function renderLoanCenter() {
    const requestList = document.querySelector("[data-loan-requests]");
    if (requestList && window.cladvestApi) {
      requestList.replaceChildren();
      try {
        const { requests } = await window.cladvestApi.get("api/loans/index.php");
        if (!requests.length) {
          const empty = document.createElement("p");
          empty.className = "empty-state";
          empty.textContent = "No requests submitted yet.";
          requestList.append(empty);
        }
        for (const request of requests) {
          const row = document.createElement("div");
          row.className = "list-row";
          const details = document.createElement("div");
          details.innerHTML = '<div class="list-title"></div><div class="list-subtitle"></div>';
          details.querySelector(".list-title").textContent = `${money(request.amount)} · ${request.months} months`;
          details.querySelector(".list-subtitle").textContent = `Submitted ${new Date(request.date).toLocaleDateString()} · ${money(request.payment)}/mo estimate${request.approvedAmount ? ` · Approved ${money(request.approvedAmount)}` : ""}`;
          const status = document.createElement("span");
          status.className = "status status-simulated";
          status.textContent = request.status;
          row.append(details, status);
          if (request.canDisburse) {
            const accept = document.createElement("button");
            accept.className = "button button-primary";
            accept.type = "button";
            accept.dataset.disburseLoan = request.id;
            accept.textContent = `Accept ${money(request.approvedAmount)} to cash balance`;
            row.append(accept);
          }
          requestList.append(row);
        }
      } catch (error) {
        const empty = document.createElement("p");
        empty.className = "notice";
        empty.textContent = error.message;
        requestList.append(empty);
      }
    }
  }

  document.querySelector("[data-loan-requests]")?.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-disburse-loan]");
    if (!button) return;
    button.disabled = true;
    try {
      await window.cladvestApi.send("api/loans/disburse.php", "POST", { loan_id: Number(button.dataset.disburseLoan) });
      await Promise.all([renderAccountSummary(), renderTransactions(), renderLoanCenter()]);
      showToast("Approved loan accepted and recorded in your cash balance.");
    } catch (error) {
      showToast(error.message);
      button.disabled = false;
    }
  });

  renderState();

  const profileForm = document.querySelector("[data-profile-form]");
  if (profileForm && window.cladvestApi) {
    window.cladvestApi.get("api/profile/index.php").then(({ profile }) => {
      profileForm.elements.name.value = [profile.first_name, profile.last_name].filter(Boolean).join(" ");
      profileForm.elements.email.value = profile.email;
      profileForm.elements.country.value = profile.country_code || "";
      const payoutDetails = profile.withdrawal_details || {};
      profileForm.elements.withdrawal_method.value = payoutDetails.preferred_method || "";
      for (const field of ["bank_name", "bank_account_name", "bank_account_number", "bank_routing_number", "bank_iban", "bank_swift_bic", "crypto_network", "crypto_wallet"]) {
        profileForm.elements[field].value = payoutDetails[field] || "";
      }
      document.querySelector("[data-profile-name]").textContent = [profile.first_name, profile.last_name].filter(Boolean).join(" ");
      document.querySelector("[data-profile-email]").textContent = profile.email;
    }).catch((error) => showToast(error.message));
  }
  profileForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!profileForm.reportValidity()) return;
    window.cladvestApi.send("api/profile/index.php", "PUT", {
      name: profileForm.elements.name.value.trim(),
      email: profileForm.elements.email.value.trim(),
      country_code: profileForm.elements.country.value,
      withdrawal_details: Object.fromEntries(["preferred_method", "bank_name", "bank_account_name", "bank_account_number", "bank_routing_number", "bank_iban", "bank_swift_bic", "crypto_network", "crypto_wallet"].map((field) => [field, profileForm.elements[field].value.trim()]))
    }).then(({ profile }) => {
      const name = [profile.first_name, profile.last_name].filter(Boolean).join(" ");
      document.querySelector("[data-profile-name]").textContent = name;
      document.querySelector("[data-profile-email]").textContent = profile.email;
      showToast("Profile saved to your account.");
    }).catch((error) => showToast(error.message));
  });

  const emailUpdatesInput = document.querySelector("[data-email-updates]");
  if (emailUpdatesInput && window.cladvestApi) {
    window.cladvestApi.get("api/profile/preferences.php").then(({ preferences }) => {
      emailUpdatesInput.checked = preferences.email_updates;
    }).catch((error) => showToast(error.message));
  }
  emailUpdatesInput?.addEventListener("change", () => {
    const requestedValue = emailUpdatesInput.checked;
    window.cladvestApi.send("api/profile/preferences.php", "PATCH", { email_updates: requestedValue })
      .then(() => showToast("Email preference saved to your account."))
      .catch((error) => {
        emailUpdatesInput.checked = !requestedValue;
        showToast(error.message);
      });
  });

  const goalForm = document.querySelector("[data-goal-form]");
  goalForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!goalForm.reportValidity()) return;
    window.cladvestApi.send("api/goals/index.php", "POST", {
      name: goalForm.querySelector("[name='goal-name']").value.trim(),
      target: goalForm.querySelector("[name='goal-target']").value,
      due_date: goalForm.querySelector("[name='goal-date']").value
    }).then(() => {
      renderGoals();
      goalForm.reset();
      showToast("Goal saved to your account.");
    }).catch((error) => {
      showToast(error.message);
    });
  });

  const recurringForm = document.querySelector("[data-recurring-form]");
  recurringForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!recurringForm.reportValidity()) return;
    window.cladvestApi.send("api/recurring-plans/index.php", "POST", {
      symbol: recurringForm.querySelector("[name='asset']").value,
      amount: recurringForm.querySelector("[name='plan-amount']").value,
      frequency: recurringForm.querySelector("[name='frequency']").value.toLowerCase()
    }).then(() => {
      renderRecurringPlans();
      recurringForm.reset();
      showToast("Schedule saved. No investment will execute automatically.");
    }).catch((error) => {
      showToast(error.message);
    });
  });

  document.querySelector("[data-recurring-list], [data-investment-schedules]")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-plan-action]");
    if (!button) return;
    const action = button.dataset.planAction;
    const method = action === "remove" ? "DELETE" : "PATCH";
    window.cladvestApi.send(`api/recurring-plans/item.php?id=${encodeURIComponent(button.dataset.planId)}`, method, { action })
      .then(() => renderRecurringPlans())
      .catch((error) => showToast(error.message));
  });

  document.querySelectorAll("form[data-funds-action='deposit']").forEach((form) => {
    const methodList = form.querySelector("[data-payment-methods]");
    const methodStatus = form.querySelector("[data-method-status]");
    const submitButton = form.querySelector("[type='submit']");
    const instructionPanel = form.parentElement.querySelector("[data-deposit-instructions-panel]");
    const instructionTitle = instructionPanel?.querySelector("[data-deposit-instructions-title]");
    const instructionText = instructionPanel?.querySelector("[data-deposit-instructions]");
    const copyInstructionsButton = instructionPanel?.querySelector("[data-copy-payment-instructions]");
    let channels = [];
    const detailLabels = {
      bank_name: "Bank",
      account_name: "Account name",
      routing_number: "Routing number",
      account_number: "Account number",
      reference: "Reference",
      recipient: "Recipient",
      cash_app_id: "Cash App ID",
      network: "Network",
      asset: "Asset",
      wallet_address: "Wallet address",
      memo: "Memo / tag"
    };
    const copyTargets = {
      bank_transfer: { label: "Copy account number", copiedLabel: "Account number", field: "account_number" },
      cash_app: { label: "Copy Cash App ID", copiedLabel: "Cash App ID", field: "cash_app_id" },
      crypto_deposit: { label: "Copy wallet address", copiedLabel: "Wallet address", field: "wallet_address" },
      crypto_wallet: { label: "Copy wallet address", copiedLabel: "Wallet address", field: "wallet_address" }
    };

    copyInstructionsButton?.addEventListener("click", async () => {
      const value = copyInstructionsButton.dataset.copyValue?.trim();
      if (!value) return;
      try {
        await navigator.clipboard.writeText(value);
        showToast(`${copyInstructionsButton.dataset.copyLabel} copied.`);
      } catch {
        showToast("Could not copy. Select the identifier and copy it manually.");
      }
    });

    const updateMethod = () => {
      const selected = form.querySelector("[name='method']:checked");
      const summary = document.querySelector("[data-selected-method]");
      const channel = channels.find((item) => item.channel_key === selected?.value);
      if (channel && summary) summary.textContent = channel.display_name;
      const detailLines = Object.entries(channel?.details || {})
        .filter(([, value]) => typeof value === "string" && value.trim())
        .map(([key, value]) => `${detailLabels[key] || key}: ${value.trim()}`);
      const instructions = [detailLines.join("\n"), channel?.instructions?.trim()].filter(Boolean).join("\n")
        || "Instructions are not configured for this method yet.";
      if (instructionTitle && instructionText) {
        instructionTitle.textContent = channel ? `${channel.display_name} instructions` : "Deposit instructions";
        instructionText.textContent = instructions;
      }
      const target = copyTargets[channel?.channel_key];
      const copyValue = target ? channel?.details?.[target.field]?.trim() : "";
      if (copyInstructionsButton) {
        copyInstructionsButton.textContent = target?.label || "No copyable identifier";
        copyInstructionsButton.dataset.copyValue = copyValue || "";
        copyInstructionsButton.dataset.copyLabel = target?.copiedLabel || "";
        copyInstructionsButton.disabled = !copyValue;
      }
    };

    form.addEventListener("change", (event) => {
      if (event.target.name !== "method") return;
      updateMethod();
    });

    if (submitButton) submitButton.disabled = true;
    let methodsTimeout;
    const methodsRequest = Promise.resolve().then(() => window.cladvestApi.get("api/payment-channels.php?direction=deposit"));
    const methodsTimedOut = new Promise((_, reject) => {
      methodsTimeout = window.setTimeout(() => reject(new Error("Deposit methods are taking too long to load. Refresh the page and sign in again.")), 10000);
    });
    Promise.race([methodsRequest, methodsTimedOut]).then((result) => {
      channels = result.channels || [];
      form.depositChannels = channels;
      const symbols = { bank_transfer: "BK", zelle: "Z", crypto: "₿", other: "••" };
      methodList.replaceChildren();
      channels.forEach((channel, index) => {
        const option = document.createElement("div");
        option.className = "method-option";
        const input = document.createElement("input");
        input.type = "radio";
        input.name = "method";
        input.value = channel.channel_key;
        input.id = `deposit-method-${index}`;
        input.required = true;
        input.checked = index === 0;
        const label = document.createElement("label");
        label.htmlFor = input.id;
        const symbol = document.createElement("span");
        symbol.className = "method-symbol";
        symbol.textContent = symbols[channel.channel_type] || "••";
        const name = document.createElement("span");
        name.textContent = channel.display_name;
        label.append(symbol, name);
        option.append(input, label);
        methodList.append(option);
      });

      methodStatus.hidden = channels.length > 0;
      if (channels.length === 0) methodStatus.textContent = "No deposit methods are currently available.";
      if (submitButton) submitButton.disabled = channels.length === 0;
      updateMethod();
    }).catch((error) => {
      methodStatus.textContent = error.message;
      if (instructionText) instructionText.textContent = "Sign in and refresh the page to load deposit instructions.";
      if (submitButton) submitButton.disabled = true;
    }).finally(() => {
      window.clearTimeout(methodsTimeout);
    });
  });

  document.querySelectorAll("form[data-funds-action] input[name='transaction_date']").forEach((input) => {
    const now = new Date();
    const localToday = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    input.max = localToday;
    input.value = input.value || localToday;
  });

  document.querySelectorAll("[data-funds-action]").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const amountInput = form.querySelector("[name='amount']");
      const amount = amountInput?.value;
      if (!Number.isFinite(Number(amount)) || Number(amount) <= 0) {
        showToast("Enter an amount greater than $0 to continue.");
        amountInput?.focus();
        return;
      }
      const action = form.dataset.fundsAction;
      const transactionDate = form.querySelector("[name='transaction_date']")?.value;
      const method = form.querySelector("[name='method']:checked")?.value || form.querySelector("[name='method']")?.value || "Payment method";
      const type = action === "withdraw" ? "withdrawal" : "deposit";
      const button = form.querySelector("[type='submit']");
      if (button) button.disabled = true;
      try {
        await window.cladvestApi.send("api/cash-movements/create.php", "POST", { type, method, amount, transaction_date: transactionDate }, {
          "Idempotency-Key": window.cladvestApi.idempotencyKey()
        });
        renderState();
        form.reset();
        if (type === "deposit") {
          const methodInput = Array.from(form.querySelectorAll("[name='method']")).find((input) => input.value === method);
          if (methodInput) methodInput.checked = true;
        }
        showToast(`${type === "deposit" ? "Deposit" : "Withdrawal"} request submitted for admin review. Your balance has not changed.`);
      } catch (error) {
        showToast(error.message);
      } finally {
        if (button) button.disabled = false;
      }
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
    loanForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!loanForm.reportValidity()) return;
      try {
        await window.cladvestApi.send("api/loans/index.php", "POST", { amount: amountInput.value, months: Number(termInput.value) });
        renderLoanCenter();
        showToast("Loan application submitted for administrator review. No funds have been transferred.");
      } catch (error) {
        showToast(error.message);
      }
    });
    updateEstimate();
  }

  const loanReminder = document.querySelector("[data-loan-reminder]");
  if (loanReminder && window.cladvestApi) {
    window.cladvestApi.get("api/profile/preferences.php")
      .then(({ preferences }) => { loanReminder.checked = preferences.loan_reminders; })
      .catch((error) => showToast(error.message));
    loanReminder.addEventListener("change", () => {
      const requestedValue = loanReminder.checked;
      window.cladvestApi.send("api/profile/preferences.php", "PATCH", { loan_reminders: requestedValue })
        .then(() => showToast("Reminder preference saved to your account."))
        .catch((error) => {
          loanReminder.checked = !requestedValue;
          showToast(error.message);
        });
    });
  }

  const chatForm = document.querySelector("[data-chat-form]");
  const chatMessages = document.querySelector("[data-chat-messages]");
  const chatConversations = document.querySelector("[data-chat-conversations]");
  if (chatForm && chatMessages && chatConversations && window.cladvestApi) {
    let activeConversation = null;
    let conversations = [];

    const renderConversations = () => {
      chatConversations.replaceChildren();
      if (!conversations.length) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = "No conversations yet.";
        chatConversations.append(empty);
        return;
      }
      for (const conversation of conversations) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "list-row";
        button.dataset.chatConversation = conversation.id;
        button.setAttribute("aria-current", String(conversation.id === activeConversation));
        const details = document.createElement("span");
        const title = document.createElement("span");
        title.className = "list-title";
        title.textContent = conversation.subject;
        const preview = document.createElement("span");
        preview.className = "list-subtitle";
        preview.textContent = conversation.last_message || conversation.category || "No messages";
        const status = document.createElement("span");
        status.className = "status status-simulated";
        status.textContent = conversation.status.replaceAll("_", " ");
        details.append(title, preview);
        button.append(details, status);
        chatConversations.append(button);
      }
    };

    const renderMessages = async (conversationId) => {
      activeConversation = conversationId;
      renderConversations();
      const selected = conversations.find((item) => item.id === conversationId);
      document.querySelector("[data-chat-subject]").textContent = selected?.subject || "New conversation";
      document.querySelector("[data-chat-status]").textContent = selected?.status.replaceAll("_", " ") || "Support inbox";
      chatMessages.replaceChildren();
      if (!conversationId) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = "Send a message to create a conversation.";
        chatMessages.append(empty);
        return;
      }
      try {
        const { messages } = await window.cladvestApi.get(`api/support/messages.php?conversation_id=${encodeURIComponent(conversationId)}`);
        if (!messages.length) {
          const empty = document.createElement("p");
          empty.className = "empty-state";
          empty.textContent = "No messages yet.";
          chatMessages.append(empty);
        }
        for (const message of messages) {
          const bubble = document.createElement("div");
          bubble.className = `chat-bubble${message.sender_type === "customer" ? " mine" : ""}`;
          if (message.sender_type === "agent" && message.sender_email) {
            const sender = document.createElement("small");
            sender.className = "chat-sender";
            sender.textContent = message.sender_email;
            bubble.append(sender, document.createTextNode(message.message));
          } else {
            bubble.textContent = message.message;
          }
          chatMessages.append(bubble);
        }
        chatMessages.scrollTop = chatMessages.scrollHeight;
      } catch (error) {
        const notice = document.createElement("p");
        notice.className = "notice";
        notice.textContent = error.message;
        chatMessages.append(notice);
      }
    };

    const refreshConversations = async (selectId = activeConversation) => {
      const result = await window.cladvestApi.get("api/support/index.php");
      conversations = result.conversations;
      if (!conversations.some((item) => item.id === selectId)) selectId = conversations[0]?.id || null;
      await renderMessages(selectId);
    };

    chatConversations.addEventListener("click", (event) => {
      const button = event.target.closest("[data-chat-conversation]");
      if (button) renderMessages(Number(button.dataset.chatConversation));
    });
    document.querySelector("[data-chat-new]")?.addEventListener("click", () => renderMessages(null));
    chatForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!chatForm.reportValidity()) return;
      const input = chatForm.elements.message;
      const message = input.value.trim();
      if (!message) return;
      const submit = chatForm.querySelector("[type='submit']");
      submit.disabled = true;
      try {
        if (activeConversation === null) {
          const subject = message.length > 80 ? `${message.slice(0, 77)}...` : message;
          const created = await window.cladvestApi.send("api/support/index.php", "POST", { subject, category: "general", message });
          await refreshConversations(created.conversation_id);
        } else {
          await window.cladvestApi.send(`api/support/messages.php?conversation_id=${encodeURIComponent(activeConversation)}`, "POST", { message });
          await refreshConversations(activeConversation);
        }
        input.value = "";
      } catch (error) {
        showToast(error.message);
      } finally {
        submit.disabled = false;
      }
    });
    refreshConversations().catch((error) => {
      chatConversations.replaceChildren();
      const notice = document.createElement("p");
      notice.className = "notice";
      notice.textContent = error.message;
      chatConversations.append(notice);
    });
  }

  const assetList = document.querySelector("[data-asset-list]");
  const detailComparison = document.querySelector("[data-asset-comparison]");
  const marketCards = document.querySelectorAll("[data-market-summary]");
  const detailSymbol = new URLSearchParams(window.location.search).get("symbol")?.toUpperCase() || "";
  const assetLogos = {
    BTC: "https://cdn.simpleicons.org/bitcoin/F7931A",
    ETH: "https://cdn.simpleicons.org/ethereum/8C8C8C",
    NVDA: "https://cdn.simpleicons.org/nvidia/76B900",
    SPACEX: "https://cdn.simpleicons.org/spacex/FFFFFF",
    TSLA: "https://cdn.simpleicons.org/tesla/E82127",
    USDT: "https://cdn.simpleicons.org/tether/26A17B",
  };
  const assetDomains = {
    BTC: "bitcoin.org",
    ETH: "ethereum.org",
    NVDA: "nvidia.com",
    SPACEX: "spacex.com",
    TSLA: "tesla.com",
    USDT: "tether.to",
  };
  let investmentAvailableCash = 0;
  let selectedInvestmentAsset = null;
  const assetTypeLabel = (type) => ({
    stock: "Public stock",
    etf: "ETF",
    bond: "Bond",
    fund: "Fund",
    crypto: "Crypto asset",
    private_equity: "Private company",
    other: "Other asset",
  }[type] || type);
  const assetMoney = (value, currency = "USD") => new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
    maximumFractionDigits: value < 1 ? 6 : 2,
  }).format(value);
  const formatChange = (value) => value === null ? "Unavailable" : `${value > 0 ? "+" : ""}${value.toFixed(2)}%`;
  async function loadInvestmentAssets() {
    if (!window.cladvestApi || (!assetList && !detailComparison && !document.querySelector("[data-asset-price]") && !marketCards.length)) return [];
    const { assets } = await window.cladvestApi.get("api/assets/index.php");
    if (document.querySelector("[data-opportunity-balance]") || document.querySelector("[data-investment-available]") || document.querySelector("[data-asset-balance]")) {
      const { summary } = await window.cladvestApi.get("api/accounts/index.php");
      investmentAvailableCash = Math.max(0, summary.cash_balance);
      document.querySelectorAll("[data-opportunity-balance], [data-investment-available], [data-asset-balance]").forEach((node) => {
        node.textContent = `${assetMoney(investmentAvailableCash)} available`;
      });
      const amountInput = document.querySelector("[data-investment-order-form] [name='amount']");
      if (amountInput) amountInput.max = investmentAvailableCash.toFixed(2);
    }
    if (assetList) {
      assetList.replaceChildren();
      if (!assets.length) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = "No active assets are available.";
        assetList.append(empty);
      }
      for (const asset of assets) {
        const card = document.createElement("article");
        card.className = "opportunity-card";
        card.dataset.filterItem = "";
        card.dataset.assetType = asset.type;
        const top = document.createElement("div");
        top.className = "opportunity-card-top";
        const identity = document.createElement("div");
        identity.className = "opportunity-identity";
        const logo = document.createElement("span");
        logo.className = "opportunity-logo";
        const fallback = document.createElement("span");
        fallback.textContent = asset.symbol.slice(0, 1);
        logo.append(fallback);
        if (assetLogos[asset.symbol]) {
          const image = document.createElement("img");
          image.src = assetLogos[asset.symbol];
          image.alt = "";
          image.loading = "lazy";
          image.addEventListener("load", () => fallback.remove(), { once: true });
          image.addEventListener("error", () => {
            if (!image.dataset.fallback) {
              image.dataset.fallback = "true";
              image.src = `https://www.google.com/s2/favicons?domain=${assetDomains[asset.symbol]}&sz=64`;
            } else {
              image.remove();
            }
          }, { once: true });
          logo.append(image);
        }
        const identityText = document.createElement("div");
        identityText.className = "opportunity-card-name";
        const name = document.createElement("a");
        name.href = `investment-detail.html?symbol=${encodeURIComponent(asset.symbol)}`;
        name.textContent = asset.name;
        const symbol = document.createElement("span");
        symbol.textContent = asset.symbol;
        identityText.append(name, symbol);
        identity.append(logo, identityText);
        const type = document.createElement("span");
        type.className = "opportunity-type";
        type.textContent = assetTypeLabel(asset.type);
        top.append(identity, type);
        const quote = document.createElement("div");
        const price = document.createElement("div");
        price.className = "opportunity-price";
        price.textContent = asset.price === null ? "Not quoted" : assetMoney(asset.price, asset.currency);
        const priceNote = document.createElement("div");
        priceNote.className = "opportunity-price-note";
        priceNote.textContent = asset.is_demo_quote ? "Stored snapshot · Not live" : asset.quote_source || "Stored quote";
        quote.append(price, priceNote);
        const bottom = document.createElement("div");
        bottom.className = "opportunity-card-bottom";
        const change = document.createElement("span");
        change.className = asset.change_percent > 0 ? "text-green small" : asset.change_percent < 0 ? "text-red small" : "muted small";
        change.textContent = `${formatChange(asset.change_percent)} vs prior quote`;
        const actions = document.createElement("div");
        actions.className = "button-row";
        const watchlistButton = document.createElement("button");
        watchlistButton.className = "button button-quiet";
        watchlistButton.type = "button";
        watchlistButton.dataset.watchlistSymbol = asset.symbol;
        watchlistButton.dataset.watchlisted = String(asset.watchlisted);
        watchlistButton.textContent = asset.watchlisted ? "Saved" : "Save";
        const link = document.createElement("a");
        link.className = "button button-primary";
        link.href = `investment-detail.html?symbol=${encodeURIComponent(asset.symbol)}`;
        link.textContent = asset.type === "private_equity" || asset.price === null ? "View details" : "View and invest";
        actions.append(watchlistButton, link);
        bottom.append(change, actions);
        card.append(top, quote, bottom);
        assetList.append(card);
      }
    }

    const detailSelect = document.querySelector("[data-investment-asset]");
    if (detailSelect) {
      const initialAsset = assets.find((asset) => asset.symbol === detailSymbol);
      detailSelect.replaceChildren(detailSelect.querySelector("option[value='']"));
      for (const asset of assets) {
        const option = document.createElement("option");
        option.value = asset.symbol;
        option.textContent = `${asset.name} (${asset.symbol})`;
        detailSelect.append(option);
      }
      detailSelect.value = initialAsset?.symbol || "";

      const updateSelectedAsset = () => {
        const selectedAsset = assets.find((asset) => asset.symbol === detailSelect.value) || null;
        selectedInvestmentAsset = selectedAsset;
        const watchButton = document.querySelector("[data-asset-watchlist]");
        const symbolInput = document.querySelector("[data-investment-order-form] [name='symbol']");
        const amountInput = document.querySelector("[data-investment-order-form] [name='amount']");
        const frequencySelect = document.querySelector("[data-investment-order-form] [name='frequency']");
        const submitButton = document.querySelector("[data-investment-order-form] [type='submit']");
        const quoteNote = document.querySelector("[data-investment-quote]");
        if (!selectedAsset) {
          document.querySelector("[data-asset-initial]").textContent = "—";
          document.querySelector("[data-asset-name]").textContent = "Select an investment";
          document.querySelector("[data-asset-symbol]").textContent = "—";
          document.querySelector("[data-asset-description]").textContent = "Choose an asset to view its details.";
          document.querySelector("[data-asset-price]").textContent = "Select an asset";
          document.querySelector("[data-asset-source]").textContent = "";
          document.querySelector("[data-asset-prior-price]").textContent = "Select an asset";
          document.querySelector("[data-asset-type]").textContent = "Select an asset";
          document.querySelector("[data-asset-exchange]").textContent = "";
          document.querySelector("[data-asset-quoted-at]").textContent = "Select an asset";
          watchButton.disabled = true;
          watchButton.dataset.symbol = "";
          watchButton.textContent = "Add to watchlist";
          if (symbolInput) symbolInput.value = "";
          if (amountInput) amountInput.disabled = true;
          if (frequencySelect) frequencySelect.disabled = true;
          if (submitButton) submitButton.disabled = true;
          if (quoteNote) quoteNote.textContent = "Select an investment to view its recorded quote.";
          document.querySelector("[data-investment-estimate]").textContent = "Select an investment first";
          return;
        }

        document.querySelector("[data-asset-initial]").textContent = selectedAsset.symbol.slice(0, 1);
        document.querySelector("[data-asset-name]").textContent = selectedAsset.name;
        document.querySelector("[data-asset-symbol]").textContent = selectedAsset.symbol;
        document.querySelector("[data-asset-description]").textContent = selectedAsset.description || assetTypeLabel(selectedAsset.type);
        document.querySelector("[data-asset-price]").textContent = selectedAsset.price === null ? "Not quoted" : assetMoney(selectedAsset.price, selectedAsset.currency);
        document.querySelector("[data-asset-source]").textContent = selectedAsset.price === null ? "No quote available" : selectedAsset.is_demo_quote ? "Stored snapshot · Not live" : selectedAsset.quote_source || "Recorded quote";
        document.querySelector("[data-asset-prior-price]").textContent = selectedAsset.previous_price === null
          ? "Not quoted"
          : assetMoney(selectedAsset.previous_price, selectedAsset.currency);
        document.querySelector("[data-asset-type]").textContent = assetTypeLabel(selectedAsset.type);
        document.querySelector("[data-asset-exchange]").textContent = selectedAsset.exchange || "No exchange listed";
        document.querySelector("[data-asset-quoted-at]").textContent = selectedAsset.quoted_at
          ? new Date(`${selectedAsset.quoted_at.replace(" ", "T")}Z`).toLocaleString()
          : "Unavailable";
        watchButton.disabled = false;
        watchButton.dataset.symbol = selectedAsset.symbol;
        watchButton.dataset.watchlisted = String(selectedAsset.watchlisted);
        watchButton.textContent = selectedAsset.watchlisted ? "Remove from watchlist" : "Add to watchlist";
        if (symbolInput) symbolInput.value = selectedAsset.symbol;
        if (amountInput) {
          amountInput.disabled = investmentAvailableCash < 1 || selectedAsset.price === null || selectedAsset.price <= 0;
          amountInput.dispatchEvent(new Event("input", { bubbles: true }));
        }
        if (frequencySelect) frequencySelect.disabled = false;
        if (quoteNote) quoteNote.textContent = selectedAsset.price === null
          ? "No stored quote is available for an estimate."
          : `Recorded price: ${assetMoney(selectedAsset.price, selectedAsset.currency)}${selectedAsset.is_demo_quote ? " · Demo only" : ""}`;
      };

      detailSelect.addEventListener("change", () => {
        const amountInput = document.querySelector("[data-investment-order-form] [name='amount']");
        if (amountInput) amountInput.value = "";
        const frequencySelect = document.querySelector("[data-investment-order-form] [name='frequency']");
        if (frequencySelect) frequencySelect.value = "monthly";
        updateSelectedAsset();
      });
      updateSelectedAsset();
    }

    if (detailComparison) {
      detailComparison.replaceChildren();
      for (const asset of assets) {
        const row = document.createElement("tr");
        for (const value of [
          `${asset.name} (${asset.symbol})`,
          assetTypeLabel(asset.type),
          asset.price === null ? "Not quoted" : assetMoney(asset.price, asset.currency),
          formatChange(asset.change_percent),
          asset.quoted_at ? new Date(`${asset.quoted_at.replace(" ", "T")}Z`).toLocaleString() : "Unavailable",
        ]) {
          const cell = document.createElement("td");
          cell.textContent = value;
          row.append(cell);
        }
        detailComparison.append(row);
      }
    }
    for (const card of marketCards) {
      const asset = assets.find((item) => item.symbol === card.dataset.marketSummary);
      const price = card.querySelector("[data-market-price]");
      const change = card.querySelector("[data-market-change]");
      price.textContent = !asset || asset.price === null ? "Not quoted" : assetMoney(asset.price, asset.currency);
      change.textContent = !asset ? "Asset unavailable" : asset.change_percent === null
        ? "No prior quote"
        : `${formatChange(asset.change_percent)} vs prior quote${asset.is_demo_quote ? " · Demo" : ""}`;
      change.classList.toggle("text-green", asset?.change_percent > 0);
      change.classList.toggle("text-red", asset?.change_percent < 0);
    }
    return assets;
  }

  assetList?.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-watchlist-symbol]");
    if (!button) return;
    const symbol = button.dataset.watchlistSymbol;
    const currentlyWatchlisted = button.dataset.watchlisted === "true";
    button.disabled = true;
    try {
      await window.cladvestApi.send("api/assets/watchlist.php", currentlyWatchlisted ? "DELETE" : "POST", { symbol });
      await loadInvestmentAssets();
      showToast(currentlyWatchlisted ? `${symbol} removed from your watchlist.` : `${symbol} added to your watchlist.`);
    } catch (error) {
      showToast(error.message);
    } finally {
      button.disabled = false;
    }
  });

  document.querySelector("[data-asset-watchlist]")?.addEventListener("click", async (event) => {
    const button = event.currentTarget;
    const symbol = button.dataset.symbol;
    const currentlyWatchlisted = button.dataset.watchlisted === "true";
    button.disabled = true;
    try {
      await window.cladvestApi.send("api/assets/watchlist.php", currentlyWatchlisted ? "DELETE" : "POST", { symbol });
      await loadInvestmentAssets();
      showToast(currentlyWatchlisted ? `${symbol} removed from your watchlist.` : `${symbol} added to your watchlist.`);
    } catch (error) {
      showToast(error.message);
    } finally {
      button.disabled = false;
    }
  });

  const investmentOrderForm = document.querySelector("[data-investment-order-form]");
  if (investmentOrderForm && window.cladvestApi) {
    const amountInput = investmentOrderForm.elements.amount;
    const estimate = document.querySelector("[data-investment-estimate]");
    const submitButton = investmentOrderForm.querySelector("[type='submit']");
    const updateInvestmentEstimate = () => {
      const amount = Number(amountInput.value);
      const price = selectedInvestmentAsset?.price;
      const valid = Number.isFinite(amount) && amount >= 1 && amount <= investmentAvailableCash && price > 0;
      amountInput.setCustomValidity(amount > investmentAvailableCash ? "Amount cannot exceed your available cash." : "");
      estimate.textContent = valid
        ? `${(amount / price).toLocaleString(undefined, { maximumFractionDigits: 8 })} ${selectedInvestmentAsset.symbol}`
        : amount > investmentAvailableCash && amount > 0 ? "Amount is above available cash" : "Enter an amount to estimate units";
      submitButton.disabled = !valid;
    };
    amountInput.addEventListener("input", updateInvestmentEstimate);
    amountInput.addEventListener("change", updateInvestmentEstimate);
    updateInvestmentEstimate();
    investmentOrderForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!investmentOrderForm.reportValidity()) return;
      const button = investmentOrderForm.querySelector("[type='submit']");
      button.disabled = true;
      try {
        await window.cladvestApi.send("api/recurring-plans/index.php", "POST", {
          symbol: investmentOrderForm.elements.symbol.value,
          amount: amountInput.value,
          frequency: investmentOrderForm.elements.frequency.value
        });
        await renderRecurringPlans();
        amountInput.value = "";
        updateInvestmentEstimate();
        showToast("Investment schedule is on. No trades will execute automatically.");
      } catch (error) {
        showToast(error.message);
      } finally {
        button.disabled = false;
      }
    });
  }

  loadInvestmentAssets().catch((error) => {
    assetList?.replaceChildren();
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent = error.message;
    assetList?.append(empty);
    if (detailComparison) {
      detailComparison.replaceChildren();
      const errorRow = document.createElement("tr");
      const errorCell = document.createElement("td");
      errorCell.colSpan = 5;
      errorCell.className = "empty-state";
      errorCell.textContent = error.message;
      errorRow.append(errorCell);
      detailComparison.append(errorRow);
    }
  });

  const opportunitySearch = document.querySelector("[data-filter-input]");
  const assetTypeFilter = document.querySelector("[data-asset-type-filter]");
  const filterOpportunities = () => {
    const query = opportunitySearch?.value.toLowerCase() || "";
    const type = assetTypeFilter?.value || "";
    document.querySelectorAll("[data-filter-item]").forEach((item) => {
      item.hidden = (type && item.dataset.assetType !== type) || !item.textContent.toLowerCase().includes(query);
    });
  };
  opportunitySearch?.addEventListener("input", filterOpportunities);
  assetTypeFilter?.addEventListener("change", filterOpportunities);

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

  const receiptDialog = document.querySelector("[data-transaction-receipt]");
  receiptDialog?.querySelectorAll("[data-receipt-close]").forEach((button) => {
    button.addEventListener("click", () => receiptDialog.close());
  });
  receiptDialog?.querySelector("[data-receipt-print]")?.addEventListener("click", () => window.print());

  document.querySelector("[data-download-statement]")?.addEventListener("click", () => {
    const visibleRows = [...document.querySelectorAll("[data-transaction-list] tr:not([hidden])")];
    if (!visibleRows.length) {
      showToast("There are no visible transactions to export.");
      return;
    }
    const escapeCsv = (value) => `"${String(value).replaceAll('"', '""')}"`;
    const csvRows = [["Date", "Type", "Method / detail", "Amount (USD)", "Status"], ...visibleRows.map((row) => [...row.cells].slice(0, 5).map((cell) => cell.textContent.trim()))];
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
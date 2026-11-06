(() => {
  if (!window.cladvestApi) return;

  const state = {
    snapshot: null,
    moneyRequests: [],
    paymentChannels: [],
    loans: [],
    conversations: [],
    selectedUserId: null,
    selectedConversationId: null,
    selectedSupportUserId: null,
    selectedAssetId: null,
    review: null,
  };
  const numberMoney = (value) => new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(Number(value) || 0);
  const setText = (selector, value) => {
    const node = document.querySelector(selector);
    if (node) node.textContent = value;
  };
  const make = (tag, className, value) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = value;
    return node;
  };
  const empty = (message) => make("p", "admin-empty", message);
  const paymentDetailFields = {
    bank_transfer: [
      ["bank_name", "Bank name"],
      ["account_name", "Account name"],
      ["routing_number", "Routing number"],
      ["account_number", "Account number"],
      ["reference", "Reference"]
    ],
    zelle: [["recipient", "Zelle recipient"], ["account_name", "Recipient name"], ["reference", "Reference"]],
    cash_app: [["cash_app_id", "Cash App ID / $Cashtag"]],
    crypto_deposit: [["network", "Network"], ["asset", "Asset"], ["wallet_address", "Wallet address"], ["memo", "Memo / tag"]],
    crypto_wallet: [["network", "Network"], ["asset", "Asset"], ["wallet_address", "Wallet address"], ["memo", "Memo / tag"]]
  };

  function notify(message, error = false) {
    document.querySelector(".admin-toast")?.remove();
    const toast = make("div", `admin-toast${error ? " is-error" : ""}`, message);
    toast.setAttribute("role", "status");
    document.body.append(toast);
    window.setTimeout(() => toast.remove(), 5000);
  }

  function showAccessError(message) {
    const notice = document.querySelector(".admin-notice");
    notice.classList.add("is-error");
    notice.textContent = message;
    document.querySelectorAll("[data-view-target]").forEach((button) => { button.disabled = true; });
    document.querySelectorAll("[data-view]").forEach((view) => { view.hidden = true; });
  }

  async function loadData() {
    const [snapshot, cashData, channelsData, loanData, supportData] = await Promise.all([
      window.cladvestApi.get("api/admin/index.php"),
      window.cladvestApi.get("api/admin/cash-movements.php"),
      window.cladvestApi.get("api/admin/payment-channels.php"),
      window.cladvestApi.get("api/admin/loans.php"),
      window.cladvestApi.get("api/support/agent.php"),
    ]);
    state.snapshot = snapshot;
    state.moneyRequests = cashData.requests;
    state.paymentChannels = channelsData.channels;
    state.loans = loanData.loans;
    state.conversations = supportData.conversations;
    if (!state.selectedUserId || !snapshot.users.some((user) => user.id === state.selectedUserId)) {
      state.selectedUserId = snapshot.users[0]?.id ?? null;
    }
    if (!state.selectedAssetId || !snapshot.assets.some((asset) => asset.id === state.selectedAssetId)) {
      state.selectedAssetId = snapshot.assets[0]?.id ?? null;
    }
    const contactableUsers = snapshot.users.filter((user) => user.status === "active" && user.id !== snapshot.admin.id);
    if (!state.selectedSupportUserId || !contactableUsers.some((user) => user.id === state.selectedSupportUserId)) {
      state.selectedSupportUserId = contactableUsers[0]?.id ?? null;
    }
    const recipientConversations = state.conversations.filter((item) => item.user_id === state.selectedSupportUserId);
    if (!recipientConversations.some((item) => item.id === state.selectedConversationId)) {
      state.selectedConversationId = recipientConversations[0]?.id ?? null;
    }
    setText("[data-admin-email]", snapshot.admin.email);
    setText("[data-admin-initials]", snapshot.admin.email.slice(0, 2).toUpperCase());
    setText("[data-admin-role]", snapshot.admin.role.replaceAll("_", " ").toUpperCase());
    document.querySelector("[data-support-form] [name='agent_identity']").value = snapshot.admin.email;
    renderAll();
  }

  function renderOverview() {
    setText("[data-stat-users]", String(state.snapshot.users.length));
    setText("[data-stat-money]", String(state.moneyRequests.filter((item) => item.status === "pending").length));
    setText("[data-stat-loans]", String(state.loans.filter((item) => ["submitted", "under_review"].includes(item.status)).length));
    setText("[data-stat-messages]", String(state.conversations.filter((item) => !["resolved", "closed"].includes(item.status)).length));
    const moneyList = document.querySelector("[data-overview-money]");
    const pending = state.moneyRequests.filter((item) => item.status === "pending").slice(0, 5);
    moneyList.classList.add("admin-money-list");
    moneyList.replaceChildren(...(pending.length ? pending.map((item) => {
      const row = make("article", "admin-money-request");
      const details = make("div", "admin-money-request-details");
      const email = make("strong", "admin-money-request-email", item.email || "Unknown account");
      const meta = make("div", "admin-money-request-meta");
      const type = make("span", `admin-money-request-type is-${item.type}`, item.type);
      const method = item.description?.replace(/\s*\(pending admin review\)$/i, "");
      const status = make("span", "admin-money-request-status", item.status);
      const amount = make("strong", "admin-money-request-amount", numberMoney(item.amount));
      if (method) meta.append(make("span", "admin-money-request-method", method));
      meta.append(type, status);
      details.append(email, meta);
      row.append(details, amount);
      return row;
    }) : [empty("No pending cash requests.")]));
    renderUserLocations();
    const loanList = document.querySelector("[data-overview-loans]");
    const pendingLoans = state.loans.filter((item) => ["submitted", "under_review"].includes(item.status)).slice(0, 5);
    loanList.replaceChildren(...(pendingLoans.length ? pendingLoans.map((item) => make("div", "admin-record-row", `${item.email} · ${numberMoney(item.requested_amount)} · ${item.status}`)) : [empty("No loans awaiting review.")]));
  }

  function renderUserLocations() {
    const list = document.querySelector("[data-user-locations]");
    if (!list) return;
    const locations = state.snapshot.user_locations || [];
    const countries = locations.filter((item) => /^[A-Z]{2}$/.test(item.country_code || "")).slice(0, 8);
    const unspecifiedCount = locations.find((item) => !/^[A-Z]{2}$/.test(item.country_code || ""))?.user_count || 0;
    const largestCount = Math.max(1, ...countries.map((item) => item.user_count), unspecifiedCount);
    const displayNames = typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "region" }) : null;
    list.replaceChildren();
    if (!countries.length && !unspecifiedCount) {
      list.append(empty("No user location data yet."));
      return;
    }
    const rows = countries.map(({ country_code: code, user_count: count }) => ({
      name: displayNames?.of(code) || code,
      code,
      count,
      className: ""
    }));
    if (unspecifiedCount) rows.push({ name: "Country not provided", code: "", count: unspecifiedCount, className: " is-unspecified" });
    for (const country of rows) {
      const row = make("div", `admin-country-row${country.className}`);
      const identity = make("span", "admin-country-identity");
      identity.append(make("strong", "admin-country-name", country.name));
      if (country.code) identity.append(make("span", "admin-country-code", country.code));
      row.append(identity, make("strong", "admin-country-count", `${country.count} ${country.count === 1 ? "account" : "accounts"}`));
      const track = make("div", "admin-country-track");
      track.setAttribute("role", "progressbar");
      track.setAttribute("aria-label", `${country.name}: ${country.count} accounts`);
      track.setAttribute("aria-valuemin", "0");
      track.setAttribute("aria-valuemax", String(largestCount));
      track.setAttribute("aria-valuenow", String(country.count));
      const fill = make("span", "");
      fill.style.width = `${Math.max(6, country.count / largestCount * 100)}%`;
      track.append(fill);
      row.append(track);
      list.append(row);
    }
  }

  function renderUsers() {
    const query = document.querySelector("[data-user-search]").value.trim().toLowerCase();
    const list = document.querySelector("[data-user-list]");
    const users = state.snapshot.users.filter((user) => `${user.first_name} ${user.last_name} ${user.email}`.toLowerCase().includes(query));
    list.replaceChildren();
    if (!users.length) list.append(empty("No users match that search."));
    for (const user of users) {
      const button = make("button", "admin-user-row");
      button.type = "button";
      button.dataset.selectUser = String(user.id);
      button.setAttribute("aria-current", String(user.id === state.selectedUserId));
      const identity = make("span", "");
      identity.append(make("strong", "list-title", `${user.first_name || ""} ${user.last_name || ""}`.trim() || user.email), make("span", "list-subtitle", `${user.email} · ${user.status}`));
      button.append(identity, make("strong", "small", numberMoney(user.cash_balance)));
      list.append(button);
    }
    const user = state.snapshot.users.find((item) => item.id === state.selectedUserId);
    const form = document.querySelector("[data-user-form]");
    if (!user || !form) return;
    form.elements.first_name.value = user.first_name || "";
    form.elements.last_name.value = user.last_name || "";
    form.elements.email.value = user.email;
    form.elements.status.value = user.status;
    form.elements.phone_country_code.value = user.phone_country_code || "";
    form.elements.phone_number.value = user.phone_number || "";
    form.elements.country_code.value = user.country_code || "";
    form.elements.region.value = user.region || "";
    form.elements.timezone.value = user.timezone || "UTC";
    form.elements.locale.value = user.locale || "en";
    form.elements.balance.value = Number(user.cash_balance).toFixed(2);
    form.elements.new_password.value = "";
    setText("[data-user-created]", user.created_at ? new Date(user.created_at).toLocaleString() : "—");
    setText("[data-user-balance]", numberMoney(user.cash_balance));
    setText("[data-user-profit]", numberMoney(user.investment_profit));
    const payoutDetails = user.withdrawal_details || {};
    const payout = document.querySelector("[data-user-payout-details]");
    if (payout) {
      payout.replaceChildren();
      const method = payoutDetails.preferred_method === "bank_transfer" ? "Bank transfer" : payoutDetails.preferred_method === "crypto" ? "Crypto wallet" : "Not selected";
      payout.append(make("div", "admin-payout-method", `Preferred method: ${method}`));
      const values = method === "Bank transfer"
        ? [["Bank", payoutDetails.bank_name], ["Account holder", payoutDetails.bank_account_name], ["Account number", payoutDetails.bank_account_number], ["Routing number", payoutDetails.bank_routing_number], ["IBAN", payoutDetails.bank_iban], ["SWIFT / BIC", payoutDetails.bank_swift_bic]]
        : [["Network", payoutDetails.crypto_network], ["Wallet address", payoutDetails.crypto_wallet]];
      const populated = values.filter(([, value]) => value);
      if (!populated.length) payout.append(empty("No withdrawal destination saved."));
      for (const [label, value] of populated) payout.append(make("div", "admin-payout-row", `${label}: ${value}`));
    }
    renderUserInvestments(user);
    const transactions = document.querySelector("[data-user-transactions]");
    const userTransactions = state.moneyRequests.filter((item) => item.user_id === user.id && ["deposit", "withdrawal"].includes(item.type));
    transactions.replaceChildren();
    if (!userTransactions.length) {
      const row = document.createElement("tr");
      const cell = make("td", "empty-state", "No deposits or withdrawals for this user.");
      cell.colSpan = 5;
      row.append(cell);
      transactions.append(row);
    }
    for (const transaction of userTransactions) {
      const row = document.createElement("tr");
      const date = transaction.created_at ? new Date(transaction.created_at).toLocaleString() : "—";
      const method = transaction.description?.replace(/\s*\(pending admin review\)$/i, "") || "—";
      for (const value of [date, transaction.type, method, numberMoney(transaction.amount), transaction.status]) {
        row.append(make("td", "", value));
      }
      transactions.append(row);
    }
  }

  function renderUserInvestments(user) {
    const assetSelect = document.querySelector("[data-user-position-asset]");
    const currentSymbol = assetSelect.value;
    const activeAssets = state.snapshot.assets.filter((asset) => asset.status === "active");
    assetSelect.replaceChildren(...activeAssets.map((asset) => {
      const option = document.createElement("option");
      option.value = asset.symbol;
      option.textContent = `${asset.name} (${asset.symbol})`;
      return option;
    }));
    if (activeAssets.some((asset) => asset.symbol === currentSymbol)) assetSelect.value = currentSymbol;
    document.querySelector("[data-user-position-add] [type='submit']").disabled = !activeAssets.length || user.status !== "active";

    const body = document.querySelector("[data-user-position-list]");
    const positions = state.snapshot.positions.filter((position) => position.user_id === user.id);
    body.replaceChildren();
    if (!positions.length) {
      const row = document.createElement("tr");
      const cell = make("td", "empty-state", "No investment positions for this user.");
      cell.colSpan = 7;
      row.append(cell);
      body.append(row);
    }
    for (const position of positions) {
      const row = document.createElement("tr");
      row.append(make("td", "", position.name), make("td", "", position.symbol));
      for (const [field, value] of [["quantity", position.quantity], ["average_cost", position.average_cost]]) {
        const cell = document.createElement("td");
        const input = document.createElement("input");
        input.className = "control admin-account-position-input";
        input.type = "number";
        input.min = "0";
        input.step = "any";
        input.value = value;
        input.dataset.accountPositionField = field;
        cell.append(input);
        row.append(cell);
      }
      const quoteCell = document.createElement("td");
      const quoteInput = document.createElement("input");
      quoteInput.className = "control admin-account-position-input";
      quoteInput.type = "number";
      quoteInput.min = "0.0000000001";
      quoteInput.step = "any";
      quoteInput.value = position.latest_price ?? "";
      quoteInput.required = true;
      quoteInput.dataset.accountAssetPrice = "";
      quoteInput.dataset.assetId = String(position.asset_id);
      quoteCell.append(quoteInput);
      row.append(quoteCell);

      const markPrice = position.latest_price ?? position.average_cost;
      const profit = position.quantity * markPrice - position.cost_basis;
      row.append(make("td", `admin-account-position-profit${profit < 0 ? " is-negative" : ""}`, numberMoney(profit)));
      const actions = make("div", "admin-account-position-actions");
      const savePosition = make("button", "button", "Save holding");
      savePosition.type = "button";
      savePosition.dataset.saveAccountPosition = String(position.id);
      const saveQuote = make("button", "button", "Save quote");
      saveQuote.type = "button";
      saveQuote.dataset.saveAccountQuote = String(position.asset_id);
      actions.append(savePosition, saveQuote);
      const actionCell = document.createElement("td");
      actionCell.append(actions);
      row.append(actionCell);
      body.append(row);
    }
  }

  function renderMoney() {
    const renderRows = (body, requests, emptyMessage, allowReview) => {
      body.replaceChildren();
      if (!requests.length) {
        const row = document.createElement("tr");
        const cell = make("td", "empty-state", emptyMessage);
        cell.colSpan = 7;
        row.append(cell);
        body.append(row);
      }
      for (const request of requests) {
        const row = document.createElement("tr");
        const values = [new Date(request.created_at).toLocaleString(), request.email, request.type, numberMoney(request.amount), request.description || "—", request.status];
        for (const value of values) row.append(make("td", "", value));
        const actions = document.createElement("td");
        if (allowReview && request.status === "pending") {
          const group = make("div", "admin-actions");
          for (const [action, label] of [["approve", "Approve"], ["reject", "Reject"]]) {
            const button = make("button", action === "approve" ? "button button-primary" : "button button-quiet", label);
            button.type = "button";
            button.dataset.reviewType = "cash";
            button.dataset.reviewId = String(request.id);
            button.dataset.reviewAction = action;
            group.append(button);
          }
          actions.append(group);
        } else {
          actions.textContent = request.reference || request.status;
        }
        row.append(actions);
        body.append(row);
      }
    };
    renderRows(
      document.querySelector("[data-money-requests]"),
      state.moneyRequests.filter((item) => item.status === "pending"),
      "No pending cash requests.",
      true
    );
    renderRows(
      document.querySelector("[data-completed-money]"),
      state.moneyRequests.filter((item) => ["completed", "approved"].includes(item.status)),
      "No completed or approved cash requests.",
      false
    );
  }

  function renderPaymentChannels() {
    const list = document.querySelector("[data-payment-channel-list]");
    list.replaceChildren();
    if (!state.paymentChannels.length) list.append(empty("No payment methods are configured."));
    for (const channel of state.paymentChannels) {
      const card = make("section", "panel admin-payment-channel");
      const heading = make("div", "admin-payment-channel-heading");
      const titleGroup = make("div", "admin-payment-channel-title-group");
      const title = make("h2", "panel-title", channel.display_name);
      const type = make("span", "admin-payment-channel-type", channel.channel_type.replaceAll("_", " "));
      const status = make("span", `admin-payment-channel-status${channel.is_active ? "" : " is-disabled"}`, channel.is_active ? "Active" : "Disabled");
      titleGroup.append(title, type);
      heading.append(titleGroup, status);

      const form = make("form", "admin-form admin-payment-channel-form");
      form.dataset.paymentChannelForm = "";
      form.dataset.paymentChannelId = String(channel.id);
      const createInput = (name, value, type = "text") => {
        const input = document.createElement("input");
        input.className = "control";
        input.name = name;
        input.type = type;
        input.value = value ?? "";
        return input;
      };
      const field = (labelText, control, fullRow = false) => {
        const label = make("label", `field${fullRow ? " admin-full-row" : ""}`);
        label.append(document.createTextNode(labelText), control);
        return label;
      };
      const checkboxField = (name, labelText, checked) => {
        const label = make("label", "field admin-check-field");
        const checkbox = createInput(name, "", "checkbox");
        checkbox.checked = checked;
        label.append(make("span", "", labelText), checkbox);
        return label;
      };

      form.append(field("Display name", createInput("display_name", channel.display_name)));
      const channelType = document.createElement("select");
      channelType.className = "control";
      channelType.name = "channel_type";
      for (const [value, label] of [["bank_transfer", "Bank transfer"], ["zelle", "Zelle"], ["crypto", "Crypto"], ["other", "Other"]]) {
        const option = make("option", "", label);
        option.value = value;
        channelType.append(option);
      }
      channelType.value = channel.channel_type;
      form.append(field("Type", channelType));
      form.append(checkboxField("deposit_enabled", "Accept deposits", channel.deposit_enabled));
      form.append(checkboxField("withdrawal_enabled", "Accept withdrawals", channel.withdrawal_enabled));
      form.append(checkboxField("is_active", "Active (visible to customers)", channel.is_active));

      const detailFields = make("div", "admin-payment-detail-fields admin-full-row");
      detailFields.append(make("h3", "admin-payment-detail-heading", "Payment details"));
      for (const [name, labelText] of paymentDetailFields[channel.channel_key] || [["recipient", "Recipient"], ["reference", "Reference"]]) {
        const input = createInput(name, channel.details?.[name] || "");
        input.dataset.channelDetail = "";
        input.maxLength = 1000;
        detailFields.append(field(labelText, input));
      }
      form.append(detailFields);

      const instructions = document.createElement("textarea");
      instructions.className = "control";
      instructions.name = "instructions";
      instructions.rows = 5;
      instructions.maxLength = 4000;
      instructions.value = channel.instructions;
      form.append(field("Additional customer note", instructions, true));
      form.append(make("p", "hint admin-full-row", "Do not enter provider passwords, API keys, or card data. Placeholder values are local-only and cannot receive funds."));
      const reason = createInput("reason", "");
      reason.minLength = 3;
      reason.maxLength = 500;
      reason.required = true;
      form.append(field("Audit reason", reason, true));
      const saveButton = make("button", "button button-primary", "Save payment method");
      saveButton.type = "submit";
      const actions = make("div", "admin-payment-channel-actions admin-full-row");
      actions.append(saveButton);
      const deleteButton = make("button", "button admin-delete-payment-channel", "Delete method");
      deleteButton.type = "button";
      deleteButton.dataset.deletePaymentChannel = "";
      actions.append(deleteButton);
      form.append(actions);

      card.append(heading, form);
      list.append(card);
    }
  }

  function renderLoans() {
    const list = document.querySelector("[data-loan-requests]");
    list.replaceChildren();
    if (!state.loans.length) list.append(empty("No loan applications."));
    for (const loan of state.loans) {
      const row = make("article", "admin-record-row");
      const details = document.createElement("div");
      details.append(make("div", "list-title", `${loan.email} · Requested ${numberMoney(loan.requested_amount)}`));
      details.append(make("div", "list-subtitle", `${loan.term_months} months · ${loan.apr ?? "—"}% APR · ${loan.status} · ${loan.submitted_at ? new Date(loan.submitted_at).toLocaleDateString() : "Not submitted"}`));
      if (loan.approved_amount !== null) details.append(make("div", "list-subtitle", `Approved: ${numberMoney(loan.approved_amount)}`));
      row.append(details);
      const actions = make("div", "admin-actions");
      if (["submitted", "under_review"].includes(loan.status)) {
        for (const [action, label] of [["approve", "Approve"], ["decline", "Decline"]]) {
          const button = make("button", action === "approve" ? "button button-primary" : "button button-quiet", label);
          button.type = "button";
          button.dataset.reviewType = "loan";
          button.dataset.reviewId = String(loan.id);
          button.dataset.reviewAction = action;
          actions.append(button);
        }
      }
      row.append(actions);
      list.append(row);
    }
  }

  function renderSupportList() {
    const list = document.querySelector("[data-support-users]");
    const recipientSelect = document.querySelector("[data-support-recipient]");
    const contactableUsers = state.snapshot.users.filter((user) => user.status === "active" && user.id !== state.snapshot.admin.id);
    recipientSelect.replaceChildren();
    if (!contactableUsers.length) {
      const option = document.createElement("option");
      option.value = "";
      option.textContent = "No other active accounts";
      recipientSelect.append(option);
      recipientSelect.disabled = true;
    } else {
      recipientSelect.disabled = false;
      for (const user of contactableUsers) {
        const option = document.createElement("option");
        option.value = String(user.id);
        option.textContent = `${user.first_name || ""} ${user.last_name || ""}`.trim() || user.email;
        if (option.textContent !== user.email) option.textContent += ` · ${user.email}`;
        recipientSelect.append(option);
      }
      recipientSelect.value = String(state.selectedSupportUserId);
    }
    list.replaceChildren();
    const recipientConversations = state.conversations.filter((item) => item.user_id === state.selectedSupportUserId);
    if (!recipientConversations.length) list.append(empty(contactableUsers.length ? "No conversations yet. Your first message will start one." : "Create another active account to start a conversation."));
    for (const conversation of recipientConversations) {
      const button = make("button", "support-user");
      button.type = "button";
      button.dataset.conversationId = String(conversation.id);
      button.setAttribute("aria-current", String(conversation.id === state.selectedConversationId));
      button.append(make("strong", "", conversation.email), make("span", "", `${conversation.subject} · ${conversation.status}`));
      list.append(button);
    }
  }

  async function renderSupportThread() {
    const conversation = state.conversations.find((item) => item.id === state.selectedConversationId);
    const messages = document.querySelector("[data-support-messages]");
    messages.replaceChildren();
    const recipient = state.snapshot.users.find((item) => item.id === state.selectedSupportUserId);
    const form = document.querySelector("[data-support-form]");
    form.querySelector("[type=submit]").disabled = !recipient;
    if (!recipient) {
      setText("[data-support-customer]", "No recipient available");
      setText("[data-support-subject]", "Create another active account to message a user.");
      form.elements.subject.value = "";
      messages.append(empty("No other active user accounts are registered."));
      return;
    }
    setText("[data-support-customer]", recipient.email);
    setText("[data-support-subject]", conversation ? `${conversation.subject} · ${conversation.status}` : "New conversation");
    form.elements.subject.value = conversation?.subject || "Account support";
    if (!conversation) {
      messages.append(empty("Send your first message to start a new conversation."));
      return;
    }
    try {
      const result = await window.cladvestApi.get(`api/support/agent.php?conversation_id=${encodeURIComponent(conversation.id)}`);
      for (const message of result.messages) {
        const fromAgent = message.sender_type === "agent";
        const bubble = make("div", `support-message${fromAgent ? " is-agent" : ""}`, message.message);
        bubble.prepend(make("small", "", `${fromAgent ? message.sender_email || "Support" : result.conversation.customer_email} · ${new Date(message.sent_at).toLocaleString()}`));
        messages.append(bubble);
      }
      if (!result.messages.length) messages.append(empty("No messages yet."));
      messages.scrollTop = messages.scrollHeight;
    } catch (error) {
      messages.append(empty(error.message));
    }
  }

  function renderSupport() {
    renderSupportList();
    renderSupportThread();
  }

  function renderPortfolio() {
    const select = document.querySelector("[data-portfolio-user]");
    const currentValue = select.value;
    select.replaceChildren(...state.snapshot.users.map((user) => {
      const option = document.createElement("option");
      option.value = String(user.id);
      option.textContent = user.email;
      return option;
    }));
    if (state.snapshot.users.some((user) => String(user.id) === currentValue)) select.value = currentValue;
    const assetSelect = document.querySelector("[data-position-asset]");
    const currentAsset = assetSelect.value;
    const activeAssets = state.snapshot.assets.filter((asset) => asset.status === "active");
    assetSelect.replaceChildren(...activeAssets.map((asset) => {
      const option = document.createElement("option");
      option.value = asset.symbol;
      option.textContent = `${asset.name} (${asset.symbol})`;
      return option;
    }));
    if (activeAssets.some((asset) => asset.symbol === currentAsset)) assetSelect.value = currentAsset;
    const userId = Number(select.value || state.snapshot.users[0]?.id);
    const positions = state.snapshot.positions.filter((position) => position.user_id === userId);
    const body = document.querySelector("[data-portfolio-rows]");
    body.replaceChildren();
    if (!positions.length) {
      const row = document.createElement("tr");
      const cell = make("td", "empty-state", "No investment positions for this user.");
      cell.colSpan = 6;
      row.append(cell);
      body.append(row);
    }
    for (const position of positions) {
      const row = document.createElement("tr");
      row.append(make("td", "", position.name), make("td", "", position.symbol));
      for (const [field, value] of [["quantity", position.quantity], ["average_cost", position.average_cost]]) {
        const cell = document.createElement("td");
        const input = document.createElement("input");
        input.className = "control admin-inline-input";
        input.type = "number";
        input.min = "0";
        input.step = "any";
        input.value = value;
        input.dataset.positionField = field;
        input.dataset.positionId = String(position.id);
        cell.append(input);
        row.append(cell);
      }
      row.append(make("td", "", position.latest_price === null ? "Not quoted" : numberMoney(position.latest_price)));
      const action = document.createElement("td");
      const button = make("button", "button", "Save");
      button.type = "button";
      button.dataset.savePosition = String(position.id);
      action.append(button);
      row.append(action);
      body.append(row);
    }
  }

  function renderAssets() {
    const list = document.querySelector("[data-opportunity-list]");
    list.replaceChildren();
    for (const asset of state.snapshot.assets) {
      const button = make("button", "admin-asset-row");
      button.type = "button";
      button.dataset.assetId = String(asset.id);
      button.setAttribute("aria-current", String(asset.id === state.selectedAssetId));
      const identity = make("span", "admin-asset-identity");
      identity.append(make("span", "admin-asset-mark", asset.symbol.slice(0, 4)));
      const labels = make("span", "admin-asset-labels");
      labels.append(make("strong", "admin-asset-name", asset.name), make("span", "admin-asset-symbol", asset.symbol));
      identity.append(labels);
      const quote = make("span", "admin-asset-quote");
      quote.append(
        make("strong", "admin-asset-price", asset.price === null ? "Not quoted" : numberMoney(asset.price)),
        make("span", `admin-asset-status is-${asset.status}`, asset.status)
      );
      button.append(identity, quote);
      list.append(button);
    }
    const asset = state.snapshot.assets.find((item) => item.id === state.selectedAssetId);
    if (!asset) return;
    const form = document.querySelector("[data-opportunity-form]");
    form.elements.name.value = asset.name;
    form.elements.symbol.value = asset.symbol;
    form.elements.type.value = asset.type;
    form.elements.status.value = asset.status;
    form.elements.is_tradeable.checked = asset.tradeable;
    form.elements.price.value = asset.price ?? "";
    form.elements.description.value = asset.description || "";
  }

  function renderAll() {
    renderOverview();
    renderUsers();
    renderMoney();
    renderPaymentChannels();
    renderLoans();
    renderSupport();
    renderPortfolio();
    renderAssets();
  }

  function openReview(type, id, action) {
    const cash = type === "cash" ? state.moneyRequests.find((item) => item.id === Number(id)) : null;
    if (cash && action === "approve") {
      if (cash.type === "withdrawal") openWithdrawalReview(cash);
      else void confirmCashApproval(cash);
      return;
    }
    state.review = { type, id: Number(id), action };
    const form = document.querySelector("[data-review-form]");
    const amountWrap = document.querySelector("[data-review-amount-wrap]");
    const loan = type === "loan" ? state.loans.find((item) => item.id === Number(id)) : null;
    amountWrap.hidden = type !== "loan" || action !== "approve";
    form.elements.approved_amount.required = !amountWrap.hidden;
    form.elements.approved_amount.value = loan?.requested_amount ?? "";
    form.elements.reason.value = "";
    setText("[data-review-title]", `${action === "approve" ? "Approve" : type === "loan" ? "Decline" : "Reject"} ${type === "loan" ? "loan" : "cash request"}`);
    setText("[data-review-description]", type === "loan"
      ? `${loan?.email} requested ${numberMoney(loan?.requested_amount)}. Approval does not transfer funds; the user must accept disbursement.`
      : `${cash?.email} requested ${cash?.type} of ${numberMoney(cash?.amount)}. Verify external settlement before approval.`);
    document.querySelector("[data-review-dialog]").showModal();
  }

  function openWithdrawalReview(cash) {
    state.review = { type: "cash", id: cash.id, action: "approve" };
    const dialog = document.querySelector("[data-withdrawal-dialog]");
    const form = dialog.querySelector("[data-withdrawal-review-form]");
    const detailsList = dialog.querySelector("[data-withdrawal-payout-details]");
    const details = cash.withdrawal_details || {};
    const method = details.preferred_method;
    const fields = method === "crypto"
      ? [["Network", details.crypto_network], ["Wallet address", details.crypto_wallet]]
      : method === "bank_transfer"
        ? [
          ["Bank name", details.bank_name],
          ["Account holder", details.bank_account_name],
          ["Account number", details.bank_account_number],
          ["Routing number", details.bank_routing_number],
          ["IBAN", details.bank_iban],
          ["SWIFT / BIC", details.bank_swift_bic],
        ]
        : [];
    const payoutFields = fields.filter(([, value]) => typeof value === "string" && value.trim());
    setText("[data-withdrawal-summary]", `${cash.email} requested ${numberMoney(cash.amount)}. ${method === "crypto" ? "Crypto wallet" : method === "bank_transfer" ? "Bank transfer" : "No preferred method selected"}. Verify the payout destination before paying.`);
    detailsList.replaceChildren();
    if (!payoutFields.length) {
      detailsList.append(empty("No withdrawal destination saved. Ask the user to add bank or crypto details in Profile before paying."));
    } else {
      for (const [label, value] of payoutFields) {
        const row = make("div", "admin-withdrawal-destination");
        const detail = make("div", "");
        detail.append(make("strong", "", label), make("code", "", value));
        row.append(detail);
        if (["Account number", "Routing number", "IBAN", "SWIFT / BIC", "Wallet address"].includes(label)) {
          const copyButton = make("button", "button button-quiet", "Copy");
          copyButton.type = "button";
          copyButton.addEventListener("click", async () => {
            try {
              await navigator.clipboard.writeText(value);
              notify(`${label} copied.`);
            } catch {
              notify(`Could not copy ${label.toLowerCase()}. Select the value and copy it manually.`, true);
            }
          });
          row.append(copyButton);
        }
        detailsList.append(row);
      }
    }
    form.reset();
    form.querySelector("[type=submit]").disabled = !payoutFields.length;
    dialog.showModal();
  }

  async function confirmCashApproval(cash) {
    const accepted = window.confirm(
      `Approve the ${numberMoney(cash.amount)} ${cash.type} request for ${cash.email}?\n\nConfirm that external settlement is complete. This will complete the request and update the app ledger.`
    );
    if (!accepted) return;
    try {
      await window.cladvestApi.send("api/admin/cash-movements.php", "PATCH", {
        movement_id: cash.id,
        action: "approve",
        settlement_confirmed: true,
      });
      await refreshAll();
      notify("Cash request approved and audited.");
    } catch (error) {
      notify(error.message, true);
    }
  }

  async function refreshAll() {
    const [snapshot, cash, channels, loans, support] = await Promise.all([
      window.cladvestApi.get("api/admin/index.php"),
      window.cladvestApi.get("api/admin/cash-movements.php"),
      window.cladvestApi.get("api/admin/payment-channels.php"),
      window.cladvestApi.get("api/admin/loans.php"),
      window.cladvestApi.get("api/support/agent.php"),
    ]);
    state.snapshot = snapshot;
    state.moneyRequests = cash.requests;
    state.paymentChannels = channels.channels;
    state.loans = loans.loans;
    state.conversations = support.conversations;
    if (!state.selectedUserId || !snapshot.users.some((user) => user.id === state.selectedUserId)) state.selectedUserId = snapshot.users[0]?.id ?? null;
    if (!state.selectedAssetId || !snapshot.assets.some((asset) => asset.id === state.selectedAssetId)) state.selectedAssetId = snapshot.assets[0]?.id ?? null;
    const contactableUsers = snapshot.users.filter((user) => user.status === "active" && user.id !== snapshot.admin.id);
    if (!contactableUsers.some((user) => user.id === state.selectedSupportUserId)) state.selectedSupportUserId = contactableUsers[0]?.id ?? null;
    const recipientConversations = state.conversations.filter((item) => item.user_id === state.selectedSupportUserId);
    if (!recipientConversations.some((item) => item.id === state.selectedConversationId)) state.selectedConversationId = recipientConversations[0]?.id ?? null;
    setText("[data-admin-email]", snapshot.admin.email);
    setText("[data-admin-initials]", snapshot.admin.email.slice(0, 2).toUpperCase());
    setText("[data-admin-role]", snapshot.admin.role.replaceAll("_", " ").toUpperCase());
    document.querySelector("[data-support-form] [name='agent_identity']").value = snapshot.admin.email;
    renderAll();
  }

  document.querySelectorAll("[data-view-target]").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll("[data-view-target]").forEach((item) => item.classList.toggle("is-active", item === button));
      document.querySelectorAll("[data-view]").forEach((view) => {
        const active = view.dataset.view === button.dataset.viewTarget;
        view.classList.toggle("is-visible", active);
        view.hidden = !active;
      });
      setText("[data-admin-title]", button.textContent.trim());
      document.querySelector("#admin-shell").classList.remove("nav-open");
      document.body.classList.remove("drawer-open");
      document.querySelector("[data-admin-menu]").setAttribute("aria-expanded", "false");
    });
  });
  document.querySelector("[data-admin-menu]").addEventListener("click", (event) => {
    const shell = document.querySelector("#admin-shell");
    const open = !shell.classList.contains("nav-open");
    shell.classList.toggle("nav-open", open);
    document.body.classList.toggle("drawer-open", open);
    event.currentTarget.setAttribute("aria-expanded", String(open));
  });
  document.querySelector("[data-admin-close]").addEventListener("click", () => {
    document.querySelector("#admin-shell").classList.remove("nav-open");
    document.body.classList.remove("drawer-open");
    document.querySelector("[data-admin-menu]").setAttribute("aria-expanded", "false");
  });
  document.querySelector("[data-user-search]").addEventListener("input", renderUsers);
  document.querySelector("[data-user-list]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-select-user]");
    if (!button) return;
    state.selectedUserId = Number(button.dataset.selectUser);
    renderUsers();
  });
  document.querySelector("[data-user-form]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const user = state.snapshot.users.find((item) => item.id === state.selectedUserId);
    if (!user) return;
    const saveButton = form.querySelector("[type=submit]");
    saveButton.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/users.php", "PATCH", {
        user_id: user.id,
        first_name: form.elements.first_name.value.trim(),
        last_name: form.elements.last_name.value.trim(),
        email: form.elements.email.value.trim(),
        status: form.elements.status.value,
        phone_country_code: form.elements.phone_country_code.value.trim(),
        phone_number: form.elements.phone_number.value.trim(),
        country_code: form.elements.country_code.value.trim(),
        region: form.elements.region.value.trim(),
        timezone: form.elements.timezone.value.trim(),
        locale: form.elements.locale.value.trim(),
        new_password: form.elements.new_password.value,
        reason: "Admin updated account details",
      });
      form.elements.new_password.value = "";
      const targetBalance = Number(form.elements.balance.value);
      const difference = Number((targetBalance - user.cash_balance).toFixed(2));
      if (difference !== 0) {
        await window.cladvestApi.send("api/admin/balance-adjustments.php", "POST", {
          user_id: user.id,
          direction: difference > 0 ? "credit" : "debit",
          amount: Math.abs(difference).toFixed(2),
          reason: "Admin cash balance adjustment from account management",
        }, { "Idempotency-Key": window.cladvestApi.idempotencyKey() });
      }
      await refreshAll();
      notify("User changes saved and audited.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      saveButton.disabled = false;
    }
  });
  document.querySelector("[data-user-position-add]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const user = state.snapshot.users.find((item) => item.id === state.selectedUserId);
    if (!user || user.status !== "active") return;
    const saveButton = form.querySelector("[type=submit]");
    saveButton.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/portfolio.php", "POST", {
        user_id: user.id,
        symbol: form.elements.symbol.value,
        quantity: form.elements.quantity.value,
        average_cost: form.elements.average_cost.value,
        reason: "Admin added holding from account details",
      });
      form.elements.quantity.value = "";
      form.elements.average_cost.value = "";
      await refreshAll();
      notify("Holding added and audited. Profit / loss recalculated.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      saveButton.disabled = false;
    }
  });
  document.querySelector("[data-user-position-list]").addEventListener("click", async (event) => {
    const savePosition = event.target.closest("[data-save-account-position]");
    const saveQuote = event.target.closest("[data-save-account-quote]");
    if (!savePosition && !saveQuote) return;
    const button = savePosition || saveQuote;
    const row = button.closest("tr");
    button.disabled = true;
    try {
      if (savePosition) {
        const fields = Object.fromEntries(Array.from(row.querySelectorAll("[data-account-position-field]"), (input) => [input.dataset.accountPositionField, input.value]));
        await window.cladvestApi.send("api/admin/portfolio.php", "PATCH", {
          position_id: Number(savePosition.dataset.saveAccountPosition),
          quantity: fields.quantity,
          average_cost: fields.average_cost,
          reason: "Admin updated holding from account details",
        });
        await refreshAll();
        notify("Holding updated and audited. Profit / loss recalculated.");
      } else {
        const asset = state.snapshot.assets.find((item) => item.id === Number(saveQuote.dataset.saveAccountQuote));
        const priceInput = row.querySelector("[data-account-asset-price]");
        if (!asset || !priceInput.reportValidity()) return;
        await window.cladvestApi.send("api/admin/assets.php", "PATCH", {
          asset_id: asset.id,
          name: asset.name,
          symbol: asset.symbol,
          asset_type: asset.type,
          status: asset.status,
          is_tradeable: asset.tradeable,
          price: priceInput.value,
          description: asset.description || "",
          reason: "Admin updated stored quote from account details",
        });
        await refreshAll();
        notify("Stored quote updated and audited. Profit / loss recalculated for all holders.");
      }
    } catch (error) {
      notify(error.message, true);
    } finally {
      button.disabled = false;
    }
  });
  document.querySelector("[data-money-requests]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-review-type]");
    if (button) openReview(button.dataset.reviewType, button.dataset.reviewId, button.dataset.reviewAction);
  });
  document.querySelector("[data-payment-channel-list]").addEventListener("submit", async (event) => {
    const form = event.target.closest("[data-payment-channel-form]");
    if (!form) return;
    event.preventDefault();
    if (!form.reportValidity()) return;
    const saveButton = form.querySelector("[type=submit]");
    saveButton.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/payment-channels.php", "PATCH", {
        channel_id: Number(form.dataset.paymentChannelId),
        display_name: form.elements.display_name.value.trim(),
        channel_type: form.elements.channel_type.value,
        details: Object.fromEntries(Array.from(form.querySelectorAll("[data-channel-detail]"), (input) => [input.name, input.value.trim()])),
        instructions: form.elements.instructions.value.trim(),
        deposit_enabled: form.elements.deposit_enabled.checked,
        withdrawal_enabled: form.elements.withdrawal_enabled.checked,
        is_active: form.elements.is_active.checked,
        reason: form.elements.reason.value.trim(),
      });
      form.elements.reason.value = "";
      await refreshAll();
      notify("Payment method settings saved and audited.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      saveButton.disabled = false;
    }
  });
  document.querySelector("[data-payment-channel-list]").addEventListener("click", async (event) => {
    const button = event.target.closest("[data-delete-payment-channel]");
    if (!button) return;
    const form = button.closest("[data-payment-channel-form]");
    if (!form) return;
    const channelName = form.elements.display_name.value.trim() || "this payment method";
    if (!window.confirm(`Delete ${channelName}? Existing transaction records will remain, but will no longer link to this method.`)) return;
    if (!form.elements.reason.reportValidity()) return;

    button.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/payment-channels.php", "DELETE", {
        channel_id: Number(form.dataset.paymentChannelId),
        reason: form.elements.reason.value.trim(),
      });
      await refreshAll();
      notify(`${channelName} deleted and audited.`);
    } catch (error) {
      notify(error.message, true);
    } finally {
      button.disabled = false;
    }
  });
  document.querySelector("[data-loan-requests]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-review-type]");
    if (button) openReview(button.dataset.reviewType, button.dataset.reviewId, button.dataset.reviewAction);
  });
  document.querySelector("[data-review-cancel]").addEventListener("click", () => document.querySelector("[data-review-dialog]").close());
  document.querySelector("[data-withdrawal-cancel]").addEventListener("click", () => document.querySelector("[data-withdrawal-dialog]").close());
  document.querySelector("[data-withdrawal-review-form]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity() || state.review?.type !== "cash" || state.review.action !== "approve") return;
    const submit = form.querySelector("[type=submit]");
    submit.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/cash-movements.php", "PATCH", {
        movement_id: state.review.id,
        action: "approve",
        settlement_confirmed: true,
      });
      document.querySelector("[data-withdrawal-dialog]").close();
      state.review = null;
      await refreshAll();
      notify("Withdrawal marked complete and audited.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      submit.disabled = false;
    }
  });
  document.querySelector("[data-review-form]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity() || !state.review) return;
    const confirm = form.querySelector("[type=submit]");
    confirm.disabled = true;
    try {
      if (state.review.type === "cash") {
        await window.cladvestApi.send("api/admin/cash-movements.php", "PATCH", {
          movement_id: state.review.id,
          action: state.review.action,
          reason: form.elements.reason.value.trim(),
        });
      } else {
        await window.cladvestApi.send("api/admin/loans.php", "PATCH", {
          loan_id: state.review.id,
          action: state.review.action,
          reason: form.elements.reason.value.trim(),
          approved_amount: form.elements.approved_amount.value,
        });
      }
      document.querySelector("[data-review-dialog]").close();
      await refreshAll();
      notify("Review decision saved and audited.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      confirm.disabled = false;
    }
  });
  document.querySelector("[data-support-users]").addEventListener("click", async (event) => {
    const button = event.target.closest("[data-conversation-id]");
    if (!button) return;
    state.selectedConversationId = Number(button.dataset.conversationId);
    renderSupportList();
    await renderSupportThread();
  });
  document.querySelector("[data-support-recipient]").addEventListener("change", () => {
    state.selectedSupportUserId = Number(document.querySelector("[data-support-recipient]").value) || null;
    state.selectedConversationId = state.conversations.find((item) => item.user_id === state.selectedSupportUserId)?.id ?? null;
    renderSupport();
  });
  document.querySelector("[data-support-form]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity() || !state.selectedSupportUserId) return;
    const sendButton = form.querySelector("[type=submit]");
    sendButton.disabled = true;
    try {
      const payload = {
        message: form.elements.message.value.trim(),
        ...(state.selectedConversationId
          ? { conversation_id: state.selectedConversationId }
          : { recipient_user_id: state.selectedSupportUserId, subject: form.elements.subject.value.trim() }),
      };
      const result = await window.cladvestApi.send("api/support/agent.php", "POST", payload);
      state.selectedConversationId = result.conversation_id;
      form.elements.message.value = "";
      await refreshAll();
      notify("Reply sent as your authenticated account.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      sendButton.disabled = false;
    }
  });
  document.querySelector("[data-portfolio-user]").addEventListener("change", renderPortfolio);
  document.querySelector("[data-portfolio-add]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const userId = Number(document.querySelector("[data-portfolio-user]").value);
    if (!userId) return;
    const submit = form.querySelector("[type=submit]");
    submit.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/portfolio.php", "POST", {
        user_id: userId,
        symbol: form.elements.symbol.value,
        quantity: form.elements.quantity.value,
        average_cost: form.elements.average_cost.value,
        reason: form.elements.reason.value.trim(),
      });
      form.elements.quantity.value = "";
      form.elements.average_cost.value = "";
      form.elements.reason.value = "";
      await refreshAll();
      notify("Manual portfolio holding added and audited. No trade or cash movement was created.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      submit.disabled = false;
    }
  });
  document.querySelector("[data-portfolio-rows]").addEventListener("click", async (event) => {
    const button = event.target.closest("[data-save-position]");
    if (!button) return;
    const id = Number(button.dataset.savePosition);
    const fields = [...document.querySelectorAll(`[data-position-id="${id}"]`)];
    const reason = window.prompt("Enter the audit reason for this portfolio change:");
    if (!reason || reason.trim().length < 3) return;
    button.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/portfolio.php", "PATCH", {
        position_id: id,
        quantity: fields.find((field) => field.dataset.positionField === "quantity").value,
        average_cost: fields.find((field) => field.dataset.positionField === "average_cost").value,
        reason: reason.trim(),
      });
      await refreshAll();
      notify("Portfolio updated and audited.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      button.disabled = false;
    }
  });
  document.querySelector("[data-opportunity-list]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-asset-id]");
    if (!button) return;
    state.selectedAssetId = Number(button.dataset.assetId);
    renderAssets();
  });
  document.querySelector("[data-opportunity-form]").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const reason = window.prompt("Enter the audit reason for this asset change:");
    if (!reason || reason.trim().length < 3) return;
    const asset = state.snapshot.assets.find((item) => item.id === state.selectedAssetId);
    if (!asset) return;
    const saveButton = form.querySelector("[type=submit]");
    saveButton.disabled = true;
    try {
      await window.cladvestApi.send("api/admin/assets.php", "PATCH", {
        asset_id: asset.id,
        name: form.elements.name.value.trim(),
        symbol: form.elements.symbol.value.trim(),
        asset_type: form.elements.type.value,
        status: form.elements.status.value,
        is_tradeable: form.elements.is_tradeable.checked,
        price: form.elements.price.value,
        description: form.elements.description.value.trim(),
        reason: reason.trim(),
      });
      await refreshAll();
      notify("Asset updated and audited.");
    } catch (error) {
      notify(error.message, true);
    } finally {
      saveButton.disabled = false;
    }
  });

  refreshAll().catch((error) => {
    showAccessError(error.message === "Administrator access required." ? error.message : `Admin data could not be loaded: ${error.message}`);
  });
})();
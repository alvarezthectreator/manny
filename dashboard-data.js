(() => {
  if (!window.cladvestApi) return;
  const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);

  async function renderDashboard() {
    try {
      const [accountData, transactionData] = await Promise.all([
        window.cladvestApi.get('api/accounts/index.php'),
        window.cladvestApi.get('api/transactions/index.php?limit=8')
      ]);
      const { summary } = accountData;
      document.querySelectorAll('[data-account-total]').forEach((node) => { node.textContent = money(summary.total_balance); });
      document.querySelectorAll('[data-account-cash]').forEach((node) => { node.textContent = money(summary.cash_balance); });
      const positions = accountData.positions || [];
      const hasMissingQuote = positions.some((position) => position.market_value === null);
      const investmentProfit = positions.length === 0
        ? 0
        : hasMissingQuote
          ? null
          : positions.reduce((total, position) => total + position.market_value - position.cost_basis, 0);
      document.querySelectorAll('[data-investment-profit]').forEach((node) => {
        node.textContent = investmentProfit === null ? '—' : money(investmentProfit);
      });
      document.querySelectorAll('[data-account-count]').forEach((node) => { node.textContent = String(summary.account_count); });

      const body = document.querySelector('[data-dashboard-transactions]');
      if (!body) return;
      body.replaceChildren();
      if (!transactionData.transactions.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.className = 'text-center text-[#a8a8a8]';
        cell.textContent = 'No account activity yet.';
        row.append(cell);
        body.append(row);
        return;
      }
      for (const transaction of transactionData.transactions) {
        const row = document.createElement('tr');
        const date = new Date(transaction.date);
        const values = [date.toLocaleDateString(), transaction.type, transaction.detail, money(transaction.amount), transaction.status];
        for (const value of values) {
          const cell = document.createElement('td');
          cell.textContent = value;
          row.append(cell);
        }
        row.cells[3].classList.add(transaction.amount < 0 ? 'text-[#ff7b82]' : 'text-[#3fcf7d]');
        body.append(row);
      }
    } catch (error) {
      const body = document.querySelector('[data-dashboard-transactions]');
      if (body) {
        body.replaceChildren();
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.className = 'text-center text-[#a8a8a8]';
        cell.textContent = error.message;
        row.append(cell);
        body.append(row);
      }
    }
  }

  async function renderMarketWidgets() {
    const form = document.querySelector('[data-swap-form]');
    const movers = document.querySelector('[data-market-movers]');
    if (!form && !movers) return;
    const fromSelect = form?.querySelector('[data-swap-from]');
    const toSelect = form?.querySelector('[data-swap-to]');
    const amountInput = form?.elements.amount;
    const output = form?.querySelector('[data-swap-output]');
    const rate = form?.querySelector('[data-swap-rate]');
    const feedback = form?.parentElement.querySelector('[data-swap-feedback]');
    const submit = form?.querySelector('[data-swap-submit]');
    let updateTimer;

    try {
      const { assets } = await window.cladvestApi.get('api/assets/index.php');
      if (movers) {
        const quoted = assets.filter((asset) => asset.price !== null && asset.change_percent !== null);
        quoted.sort((left, right) => Math.abs(right.change_percent) - Math.abs(left.change_percent));
        movers.replaceChildren();
        for (const asset of quoted.slice(0, 3)) {
          const row = document.createElement('li');
          row.className = 'd-flex align-items-center justify-content-between py-2 border-b border-[#232323]';
          const label = document.createElement('span');
          label.className = 'text-[12px] text-white';
          label.textContent = `${asset.name} · ${asset.symbol}`;
          const quote = document.createElement('span');
          quote.className = 'text-end text-[11px] text-[#a8a8a8]';
          quote.textContent = `${money(asset.price)} · ${asset.change_percent > 0 ? '+' : ''}${asset.change_percent.toFixed(2)}%`;
          row.append(label, quote);
          movers.append(row);
        }
        if (!quoted.length) {
          const empty = document.createElement('li');
          empty.className = 'py-3 text-[11px] text-[#a8a8a8]';
          empty.textContent = 'No stored quote history.';
          movers.append(empty);
        }
      }
      if (!form) return;
      const cryptoAssets = assets.filter((asset) => asset.type === 'crypto' && asset.price !== null);
      for (const asset of cryptoAssets) {
        for (const select of [fromSelect, toSelect]) {
          const option = document.createElement('option');
          option.value = asset.symbol;
          option.textContent = `${asset.name} (${asset.symbol})`;
          select.append(option);
        }
      }
      if (!cryptoAssets.length) {
        output.textContent = 'No stored crypto quotes';
        feedback.textContent = 'A quote is required to preview a swap.';
        return;
      }
      fromSelect.value = cryptoAssets.some((asset) => asset.symbol === 'ETH') ? 'ETH' : cryptoAssets[0].symbol;
      toSelect.value = cryptoAssets.some((asset) => asset.symbol === 'USDT')
        ? 'USDT'
        : cryptoAssets.find((asset) => asset.symbol !== fromSelect.value)?.symbol || fromSelect.value;

      const refreshQuote = async () => {
        submit.disabled = true;
        try {
          const query = new URLSearchParams({ from: fromSelect.value, to: toSelect.value, amount: amountInput.value });
          const { quote } = await window.cladvestApi.get(`api/swaps/index.php?${query}`);
          output.textContent = `${quote.output_quantity.toLocaleString(undefined, { maximumFractionDigits: 8 })} ${quote.to}`;
          rate.textContent = `1 ${quote.from} = ${quote.rate.toLocaleString(undefined, { maximumFractionDigits: 8 })} ${quote.to} · ${quote.is_demo_quote ? 'Demo quote' : 'Stored quote'}`;
          feedback.textContent = `Quote time: ${quote.from_quoted_at}. No provider fee included.`;
          submit.disabled = false;
        } catch (error) {
          output.textContent = 'Quote unavailable';
          rate.textContent = '';
          feedback.textContent = error.message;
        }
      };
      const scheduleQuote = () => {
        window.clearTimeout(updateTimer);
        updateTimer = window.setTimeout(refreshQuote, 180);
      };
      for (const control of [fromSelect, toSelect, amountInput]) control.addEventListener('input', scheduleQuote);
      document.querySelector('[data-swap-switch]')?.addEventListener('click', () => {
        const previousFrom = fromSelect.value;
        fromSelect.value = toSelect.value;
        toSelect.value = previousFrom;
        scheduleQuote();
      });
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity() || submit.disabled) return;
        submit.disabled = true;
        try {
          const result = await window.cladvestApi.send('api/swaps/index.php', 'POST', {
            from: fromSelect.value,
            to: toSelect.value,
            amount: amountInput.value
          }, { 'Idempotency-Key': window.cladvestApi.idempotencyKey() });
          feedback.textContent = result.message;
          await renderSwapHistory();
        } catch (error) {
          feedback.textContent = error.message;
        } finally {
          submit.disabled = false;
        }
      });
      await Promise.all([refreshQuote(), renderSwapHistory()]);
    } catch (error) {
      if (output) output.textContent = 'Quote unavailable';
      if (feedback) feedback.textContent = error.message;
      if (movers) {
        movers.replaceChildren();
        const row = document.createElement('li');
        row.className = 'py-3 text-[11px] text-[#a8a8a8]';
        row.textContent = error.message;
        movers.append(row);
      }
    }

    async function renderSwapHistory() {
      const history = document.querySelector('[data-swap-history]');
      if (!history) return;
      const { simulations } = await window.cladvestApi.get('api/swaps/index.php');
      history.replaceChildren();
      for (const swap of simulations.slice(0, 3)) {
        const row = document.createElement('div');
        row.className = 'border-t border-[#232323] py-2 text-[10px] text-[#a8a8a8]';
        row.textContent = `${swap.input_quantity} ${swap.from} → ${swap.output_quantity} ${swap.to} · Preview saved`;
        history.append(row);
      }
    }
  }

  async function renderInvestmentStatus() {
    const banner = document.querySelector('[data-dashboard-investment-status]');
    if (!banner) return;
    try {
      const { plans } = await window.cladvestApi.get('api/recurring-plans/index.php');
      const activePlans = plans.filter((plan) => plan.active);
      if (!activePlans.length) {
        banner.hidden = true;
        return;
      }
      const message = activePlans.length === 1
        ? `${activePlans[0].symbol} schedule is on · ${money(activePlans[0].amount)} ${activePlans[0].frequency.toLowerCase()}.`
        : `${activePlans.length} investment schedules are on.`;
      banner.querySelector('[data-dashboard-investment-copy]').textContent = message;
      banner.hidden = false;
    } catch {
      banner.hidden = true;
    }
  }

  async function renderNotifications() {
    const panel = document.querySelector('[data-dashboard-notifications]');
    const list = document.querySelector('[data-dashboard-notification-items]');
    if (!panel || !list) return;
    try {
      await window.cladvestApi.get('api/recurring-plans/index.php');
      const { notifications } = await window.cladvestApi.get('api/notifications/index.php');
      list.replaceChildren();
      for (const notification of notifications) {
        const item = document.createElement('article');
        const title = document.createElement('strong');
        title.textContent = notification.title;
        const body = document.createElement('p');
        body.className = 'm-0';
        body.textContent = notification.body || '';
        item.append(title, body);
        list.append(item);
      }
      panel.hidden = notifications.length === 0;
    } catch {
      panel.hidden = true;
    }
  }

  document.querySelectorAll('[data-dismiss-banners]').forEach((button) => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await window.cladvestApi.send('api/notifications/index.php', 'DELETE', {});
        document.querySelectorAll('[data-dashboard-notifications], [data-dashboard-investment-status]').forEach((banner) => { banner.hidden = true; });
        const list = document.querySelector('[data-dashboard-notification-items]');
        if (list) list.replaceChildren();
      } catch {
        await Promise.all([renderNotifications(), renderInvestmentStatus()]);
      } finally {
        button.disabled = false;
      }
    });
  });

  renderDashboard();
  renderMarketWidgets();
  renderInvestmentStatus();
  renderNotifications();
})();
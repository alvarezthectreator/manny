(() => {
  if (!window.cladvestApi) return;
  const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);

  async function renderPortfolio() {
    const body = document.querySelector('[data-portfolio-positions]');
    if (!body) return;
    try {
      const { positions, summary } = await window.cladvestApi.get('api/accounts/index.php');
      body.replaceChildren();
      document.querySelectorAll('[data-account-count]').forEach((node) => { node.textContent = String(summary.account_count); });
      document.querySelector('[data-position-count]').textContent = `${positions.length} ${positions.length === 1 ? 'position' : 'positions'}`;
      if (!positions.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 6;
        cell.className = 'empty-state';
        cell.textContent = 'No investment positions recorded.';
        row.append(cell);
        body.append(row);
        return;
      }
      for (const position of positions) {
        const row = document.createElement('tr');
        const assetCell = document.createElement('td');
        const asset = document.createElement('div');
        asset.className = 'list-primary';
        const icon = document.createElement('span');
        icon.className = 'asset-icon';
        icon.textContent = position.symbol.slice(0, 1);
        const label = document.createElement('span');
        const name = document.createElement('span');
        name.className = 'list-title';
        name.textContent = position.name;
        const symbol = document.createElement('span');
        symbol.className = 'list-subtitle';
        symbol.textContent = position.symbol;
        label.append(name, document.createElement('br'), symbol);
        asset.append(icon, label);
        assetCell.append(asset);
        const values = [
          position.quantity.toLocaleString(undefined, { maximumFractionDigits: 8 }),
          money(position.average_cost),
          position.market_value === null ? 'No quote' : money(position.market_value),
          position.market_value === null ? 'No quote' : money(position.market_value - position.cost_basis),
          'Investment'
        ];
        row.append(assetCell);
        for (const value of values) {
          const cell = document.createElement('td');
          cell.textContent = value;
          row.append(cell);
        }
        body.append(row);
      }
    } catch (error) {
      body.replaceChildren();
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 6;
      cell.className = 'empty-state';
      cell.textContent = error.message;
      row.append(cell);
      body.append(row);
    }
  }

  renderPortfolio();
})();
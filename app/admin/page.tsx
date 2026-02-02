// Icones SVG inline
const Icons = {
  DollarSign: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="12" y1="1" x2="12" y2="23" />
      <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
    </svg>
  ),
  ShoppingCart: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="9" cy="21" r="1" />
      <circle cx="20" cy="21" r="1" />
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    </svg>
  ),
  Package: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="16.5" y1="9.4" x2="7.5" y2="4.21" />
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
      <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
      <line x1="12" y1="22.08" x2="12" y2="12" />
    </svg>
  ),
  Users: () => (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  ),
  TrendingUp: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
      <polyline points="17 6 23 6 23 12" />
    </svg>
  ),
  TrendingDown: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
      <polyline points="17 18 23 18 23 12" />
    </svg>
  ),
  ArrowUpRight: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="7" y1="17" x2="17" y2="7" />
      <polyline points="7 7 17 7 17 17" />
    </svg>
  ),
}

const stats = [
  {
    title: "Vendas Totais",
    value: "R$ 45.890,00",
    change: "+12.5%",
    trend: "up",
    icon: Icons.DollarSign,
    color: "gold",
  },
  {
    title: "Pedidos",
    value: "156",
    change: "+8.2%",
    trend: "up",
    icon: Icons.ShoppingCart,
    color: "blue",
  },
  {
    title: "Produtos",
    value: "324",
    change: "+3.1%",
    trend: "up",
    icon: Icons.Package,
    color: "green",
  },
  {
    title: "Clientes",
    value: "1.289",
    change: "-2.4%",
    trend: "down",
    icon: Icons.Users,
    color: "purple",
  },
]

const recentOrders = [
  {
    id: "#12345",
    customer: "Maria Silva",
    product: "Perfume Channel N5",
    total: "R$ 450,00",
    status: "Entregue",
    statusColor: "success",
  },
  {
    id: "#12346",
    customer: "Joao Santos",
    product: "Kit Perfumes Masculinos",
    total: "R$ 890,00",
    status: "Em transito",
    statusColor: "warning",
  },
  {
    id: "#12347",
    customer: "Ana Costa",
    product: "Perfume Dior Sauvage",
    total: "R$ 650,00",
    status: "Processando",
    statusColor: "info",
  },
  {
    id: "#12348",
    customer: "Pedro Lima",
    product: "Perfume Versace",
    total: "R$ 380,00",
    status: "Pendente",
    statusColor: "neutral",
  },
  {
    id: "#12349",
    customer: "Carla Mendes",
    product: "Perfume Gucci Bloom",
    total: "R$ 520,00",
    status: "Entregue",
    statusColor: "success",
  },
]

const topProducts = [
  { name: "Perfume Channel N5", sales: 89, revenue: "R$ 40.050,00" },
  { name: "Dior Sauvage", sales: 76, revenue: "R$ 49.400,00" },
  { name: "Versace Eros", sales: 65, revenue: "R$ 24.700,00" },
  { name: "Gucci Bloom", sales: 54, revenue: "R$ 28.080,00" },
]

export default function AdminDashboard() {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "24px" }}>
      {/* Page Header */}
      <div className="page-header">
        <div>
          <h1 className="text-balance">Dashboard</h1>
          <p className="page-subtitle">Visao geral do seu e-commerce</p>
        </div>
        <div>
          <select className="form-select" style={{ width: "auto", minWidth: "180px" }}>
            <option>Ultimos 7 dias</option>
            <option>Ultimos 30 dias</option>
            <option>Ultimos 90 dias</option>
            <option>Este ano</option>
          </select>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="stats-grid">
        {stats.map((stat) => {
          const Icon = stat.icon
          return (
            <div key={stat.title} className="stat-card">
              <div>
                <div className={`stat-icon ${stat.color}`}>
                  <Icon />
                </div>
                <div className="stat-content">
                  <p className="stat-value">{stat.value}</p>
                  <p className="stat-label">{stat.title}</p>
                </div>
              </div>
              <div className={`stat-change ${stat.trend}`}>
                {stat.trend === "up" ? <Icons.TrendingUp /> : <Icons.TrendingDown />}
                {stat.change}
              </div>
            </div>
          )
        })}
      </div>

      {/* Content Grid */}
      <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: "24px" }}>
        {/* Recent Orders */}
        <div className="card">
          <div className="card-header">
            <h2>
              <span className="card-header-icon"><Icons.ShoppingCart /></span>
              Pedidos Recentes
            </h2>
            <button className="btn btn-ghost btn-sm">
              Ver todos <Icons.ArrowUpRight />
            </button>
          </div>
          <div className="table-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Pedido</th>
                  <th>Cliente</th>
                  <th>Produto</th>
                  <th>Total</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {recentOrders.map((order) => (
                  <tr key={order.id}>
                    <td style={{ fontWeight: 500 }}>{order.id}</td>
                    <td>{order.customer}</td>
                    <td>{order.product}</td>
                    <td style={{ fontWeight: 600 }}>{order.total}</td>
                    <td>
                      <span className={`badge badge-${order.statusColor}`}>
                        {order.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Top Products */}
        <div className="card">
          <div className="card-header">
            <h2>
              <span className="card-header-icon"><Icons.Package /></span>
              Produtos Mais Vendidos
            </h2>
          </div>
          <div className="card-body">
            <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
              {topProducts.map((product, index) => (
                <div key={product.name} style={{ display: "flex", alignItems: "center", gap: "16px" }}>
                  <div style={{
                    width: "40px",
                    height: "40px",
                    borderRadius: "12px",
                    background: "linear-gradient(135deg, rgba(199, 163, 51, 0.2), rgba(199, 163, 51, 0.1))",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    color: "#C7A333",
                    fontWeight: 700,
                    fontSize: "0.9rem",
                    flexShrink: 0
                  }}>
                    {index + 1}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <p style={{ fontWeight: 500, fontSize: "0.9rem", margin: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", color: "var(--text-primary)" }}>
                      {product.name}
                    </p>
                    <p style={{ fontSize: "0.8rem", color: "var(--text-muted)", margin: 0 }}>
                      {product.sales} vendas
                    </p>
                  </div>
                  <div style={{ textAlign: "right", flexShrink: 0 }}>
                    <p style={{ fontWeight: 600, fontSize: "0.9rem", margin: 0, color: "var(--text-primary)" }}>
                      {product.revenue}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="quick-actions-grid">
        {[
          { icon: Icons.Package, label: "Novo Produto" },
          { icon: Icons.ShoppingCart, label: "Ver Pedidos" },
          { icon: Icons.Users, label: "Clientes" },
          { icon: Icons.DollarSign, label: "Relatorios" },
        ].map((action) => (
          <button key={action.label} className="quick-action-btn">
            <div className="quick-action-icon">
              <action.icon />
            </div>
            <span className="quick-action-label">{action.label}</span>
          </button>
        ))}
      </div>
    </div>
  )
}

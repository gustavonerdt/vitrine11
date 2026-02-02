import {
  Package,
  ShoppingCart,
  Users,
  DollarSign,
  TrendingUp,
  TrendingDown,
  Eye,
  ArrowUpRight,
} from "lucide-react"
import Link from "next/link"

const stats = [
  {
    title: "Vendas Totais",
    value: "R$ 45.890,00",
    change: "+12.5%",
    trend: "up",
    icon: DollarSign,
    color: "amber",
  },
  {
    title: "Pedidos",
    value: "156",
    change: "+8.2%",
    trend: "up",
    icon: ShoppingCart,
    color: "blue",
  },
  {
    title: "Produtos",
    value: "324",
    change: "+3.1%",
    trend: "up",
    icon: Package,
    color: "green",
  },
  {
    title: "Clientes",
    value: "1.289",
    change: "-2.4%",
    trend: "down",
    icon: Users,
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
    statusColor: "green",
  },
  {
    id: "#12346",
    customer: "Joao Santos",
    product: "Kit Perfumes Masculinos",
    total: "R$ 890,00",
    status: "Em transito",
    statusColor: "amber",
  },
  {
    id: "#12347",
    customer: "Ana Costa",
    product: "Perfume Dior Sauvage",
    total: "R$ 650,00",
    status: "Processando",
    statusColor: "blue",
  },
  {
    id: "#12348",
    customer: "Pedro Lima",
    product: "Perfume Versace",
    total: "R$ 380,00",
    status: "Pendente",
    statusColor: "zinc",
  },
  {
    id: "#12349",
    customer: "Carla Mendes",
    product: "Perfume Gucci Bloom",
    total: "R$ 520,00",
    status: "Entregue",
    statusColor: "green",
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
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-zinc-900">Dashboard</h1>
          <p className="text-zinc-500 text-sm mt-1">Visao geral do seu e-commerce</p>
        </div>
        <div className="flex items-center gap-3">
          <select className="px-4 py-2 bg-white border border-zinc-200 rounded-xl text-sm text-zinc-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            <option>Ultimos 7 dias</option>
            <option>Ultimos 30 dias</option>
            <option>Ultimos 90 dias</option>
            <option>Este ano</option>
          </select>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat) => {
          const Icon = stat.icon
          const colorClasses = {
            amber: "bg-amber-100 text-amber-600",
            blue: "bg-blue-100 text-blue-600",
            green: "bg-green-100 text-green-600",
            purple: "bg-purple-100 text-purple-600",
          }
          return (
            <div
              key={stat.title}
              className="bg-white rounded-2xl border border-zinc-200 p-5 hover:shadow-lg hover:border-zinc-300 transition-all duration-200"
            >
              <div className="flex items-start justify-between">
                <div
                  className={`w-12 h-12 rounded-xl flex items-center justify-center ${colorClasses[stat.color as keyof typeof colorClasses]}`}
                >
                  <Icon className="w-6 h-6" />
                </div>
                <div
                  className={`flex items-center gap-1 text-sm font-medium ${stat.trend === "up" ? "text-green-600" : "text-red-600"}`}
                >
                  {stat.trend === "up" ? (
                    <TrendingUp className="w-4 h-4" />
                  ) : (
                    <TrendingDown className="w-4 h-4" />
                  )}
                  {stat.change}
                </div>
              </div>
              <div className="mt-4">
                <p className="text-2xl font-bold text-zinc-900">{stat.value}</p>
                <p className="text-sm text-zinc-500 mt-1">{stat.title}</p>
              </div>
            </div>
          )
        })}
      </div>

      {/* Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Orders */}
        <div className="lg:col-span-2 bg-white rounded-2xl border border-zinc-200 overflow-hidden">
          <div className="px-5 py-4 border-b border-zinc-200 flex items-center justify-between bg-zinc-50">
            <h2 className="font-semibold text-zinc-900 flex items-center gap-2">
              <ShoppingCart className="w-5 h-5 text-amber-600" />
              Pedidos Recentes
            </h2>
            <Link
              href="/admin/orders"
              className="text-sm text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1"
            >
              Ver todos <ArrowUpRight className="w-4 h-4" />
            </Link>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-zinc-100">
                  <th className="text-left py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                    Pedido
                  </th>
                  <th className="text-left py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                    Cliente
                  </th>
                  <th className="text-left py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider hidden md:table-cell">
                    Produto
                  </th>
                  <th className="text-left py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                    Total
                  </th>
                  <th className="text-left py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                    Status
                  </th>
                </tr>
              </thead>
              <tbody>
                {recentOrders.map((order) => (
                  <tr key={order.id} className="border-b border-zinc-50 hover:bg-zinc-50 transition-colors">
                    <td className="py-3 px-5 text-sm font-medium text-zinc-900">{order.id}</td>
                    <td className="py-3 px-5 text-sm text-zinc-600">{order.customer}</td>
                    <td className="py-3 px-5 text-sm text-zinc-600 hidden md:table-cell">{order.product}</td>
                    <td className="py-3 px-5 text-sm font-semibold text-zinc-900">{order.total}</td>
                    <td className="py-3 px-5">
                      <span
                        className={`inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                          ${order.statusColor === "green" ? "bg-green-100 text-green-700" : ""}
                          ${order.statusColor === "amber" ? "bg-amber-100 text-amber-700" : ""}
                          ${order.statusColor === "blue" ? "bg-blue-100 text-blue-700" : ""}
                          ${order.statusColor === "zinc" ? "bg-zinc-100 text-zinc-700" : ""}
                        `}
                      >
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
        <div className="bg-white rounded-2xl border border-zinc-200 overflow-hidden">
          <div className="px-5 py-4 border-b border-zinc-200 flex items-center justify-between bg-zinc-50">
            <h2 className="font-semibold text-zinc-900 flex items-center gap-2">
              <Package className="w-5 h-5 text-amber-600" />
              Produtos Mais Vendidos
            </h2>
          </div>
          <div className="p-5 space-y-4">
            {topProducts.map((product, index) => (
              <div key={product.name} className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-100 to-amber-200 flex items-center justify-center text-amber-700 font-bold text-sm">
                  {index + 1}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-zinc-900 truncate">{product.name}</p>
                  <p className="text-sm text-zinc-500">{product.sales} vendas</p>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-zinc-900">{product.revenue}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <Link
          href="/admin/products"
          className="flex flex-col items-center gap-3 p-6 bg-white rounded-2xl border border-zinc-200 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/10 transition-all duration-200 group"
        >
          <div className="w-14 h-14 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-500 transition-colors">
            <Package className="w-7 h-7 text-amber-600 group-hover:text-black transition-colors" />
          </div>
          <span className="font-medium text-zinc-700 group-hover:text-zinc-900 transition-colors">Novo Produto</span>
        </Link>
        <Link
          href="/admin/orders"
          className="flex flex-col items-center gap-3 p-6 bg-white rounded-2xl border border-zinc-200 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/10 transition-all duration-200 group"
        >
          <div className="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center group-hover:bg-amber-500 transition-colors">
            <ShoppingCart className="w-7 h-7 text-blue-600 group-hover:text-black transition-colors" />
          </div>
          <span className="font-medium text-zinc-700 group-hover:text-zinc-900 transition-colors">Ver Pedidos</span>
        </Link>
        <Link
          href="/admin/users"
          className="flex flex-col items-center gap-3 p-6 bg-white rounded-2xl border border-zinc-200 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/10 transition-all duration-200 group"
        >
          <div className="w-14 h-14 rounded-xl bg-green-100 flex items-center justify-center group-hover:bg-amber-500 transition-colors">
            <Users className="w-7 h-7 text-green-600 group-hover:text-black transition-colors" />
          </div>
          <span className="font-medium text-zinc-700 group-hover:text-zinc-900 transition-colors">Clientes</span>
        </Link>
        <Link
          href="/admin/stats"
          className="flex flex-col items-center gap-3 p-6 bg-white rounded-2xl border border-zinc-200 hover:border-amber-400 hover:shadow-lg hover:shadow-amber-500/10 transition-all duration-200 group"
        >
          <div className="w-14 h-14 rounded-xl bg-purple-100 flex items-center justify-center group-hover:bg-amber-500 transition-colors">
            <Eye className="w-7 h-7 text-purple-600 group-hover:text-black transition-colors" />
          </div>
          <span className="font-medium text-zinc-700 group-hover:text-zinc-900 transition-colors">Relatorios</span>
        </Link>
      </div>
    </div>
  )
}

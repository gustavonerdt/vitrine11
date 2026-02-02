import Link from "next/link"
import { 
  ShoppingBag, 
  CreditCard, 
  Truck, 
  CheckCircle2, 
  ArrowRight,
  Sparkles,
  Shield,
  Zap
} from "lucide-react"

export default function Home() {
  return (
    <div className="min-h-screen bg-[#fef9e0]">
      {/* Header */}
      <header className="border-b border-amber-200/50 bg-white/80 backdrop-blur-sm sticky top-0 z-50">
        <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-amber-500/20">
              V
            </div>
            <span className="text-xl font-bold bg-gradient-to-r from-amber-600 to-amber-500 bg-clip-text text-transparent">
              Vitrine11
            </span>
          </div>
          <Link 
            href="/admin" 
            className="inline-flex items-center gap-2 px-4 py-2 bg-zinc-900 text-white text-sm font-medium rounded-lg hover:bg-zinc-800 transition-colors"
          >
            Painel Admin
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
      </header>

      {/* Hero Section */}
      <section className="py-20 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 px-4 py-2 bg-amber-100 rounded-full text-amber-700 text-sm font-medium mb-6">
            <Sparkles className="w-4 h-4" />
            E-commerce Premium
          </div>
          <h1 className="text-4xl md:text-5xl font-bold text-zinc-900 mb-6 text-balance leading-tight">
            Vitrine E-commerce
            <span className="block text-amber-600">Configurado com Sucesso</span>
          </h1>
          <p className="text-lg text-zinc-600 mb-10 max-w-2xl mx-auto text-pretty leading-relaxed">
            Este projeto e-commerce PHP foi configurado com sucesso. 
            Para visualizar a loja completa, faca o deploy em um servidor PHP com MySQL.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link
              href="/admin"
              className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-semibold rounded-xl shadow-lg shadow-amber-500/25 hover:shadow-xl hover:shadow-amber-500/30 hover:-translate-y-0.5 transition-all"
            >
              Acessar Painel Admin
              <ArrowRight className="w-5 h-5" />
            </Link>
          </div>
        </div>
      </section>

      {/* Funnel Steps */}
      <section className="py-16 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="bg-white rounded-3xl p-8 md:p-10 shadow-xl border border-zinc-100">
            <div className="flex items-center gap-3 mb-8">
              <div className="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                <ShoppingBag className="w-6 h-6 text-amber-600" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-zinc-900">Funil de Compra Configurado</h2>
                <p className="text-sm text-zinc-500">6 etapas otimizadas para conversao</p>
              </div>
            </div>
            
            <div className="grid gap-4">
              {[
                { step: 1, title: "index.php", desc: "Pagina inicial com produtos" },
                { step: 2, title: "product.php", desc: "Detalhes do produto" },
                { step: 3, title: "carrinho.php", desc: "Carrinho de compras" },
                { step: 4, title: "checkout-entrega.php", desc: "Dados de entrega" },
                { step: 5, title: "checkout-pagamento.php", desc: "Pagamento MP" },
                { step: 6, title: "obrigado.php", desc: "Confirmacao do pedido" },
              ].map((item, index) => (
                <div 
                  key={item.step}
                  className="flex items-center gap-4 p-4 rounded-xl bg-zinc-50 hover:bg-amber-50 transition-colors group"
                >
                  <div className="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-transform">
                    {item.step}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-zinc-900">{item.title}</p>
                    <p className="text-sm text-zinc-500">{item.desc}</p>
                  </div>
                  <CheckCircle2 className="w-5 h-5 text-emerald-500 flex-shrink-0" />
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Features Grid */}
      <section className="py-16 px-4 pb-24">
        <div className="max-w-4xl mx-auto">
          <div className="grid sm:grid-cols-2 gap-4">
            {/* Mercado Pago */}
            <div className="bg-blue-50 rounded-2xl p-6 border border-blue-100">
              <div className="w-12 h-12 rounded-xl bg-blue-500 flex items-center justify-center mb-4">
                <CreditCard className="w-6 h-6 text-white" />
              </div>
              <h3 className="font-bold text-blue-900 text-lg mb-2">API Mercado Pago</h3>
              <p className="text-blue-700 text-sm">PIX + Cartao de Credito integrados</p>
            </div>
            
            {/* Correios */}
            <div className="bg-orange-50 rounded-2xl p-6 border border-orange-100">
              <div className="w-12 h-12 rounded-xl bg-orange-500 flex items-center justify-center mb-4">
                <Truck className="w-6 h-6 text-white" />
              </div>
              <h3 className="font-bold text-orange-900 text-lg mb-2">API Correios</h3>
              <p className="text-orange-700 text-sm">Calculo de frete automatico</p>
            </div>
            
            {/* Seguranca */}
            <div className="bg-emerald-50 rounded-2xl p-6 border border-emerald-100">
              <div className="w-12 h-12 rounded-xl bg-emerald-500 flex items-center justify-center mb-4">
                <Shield className="w-6 h-6 text-white" />
              </div>
              <h3 className="font-bold text-emerald-900 text-lg mb-2">Seguranca</h3>
              <p className="text-emerald-700 text-sm">Transacoes protegidas e criptografadas</p>
            </div>
            
            {/* Performance */}
            <div className="bg-violet-50 rounded-2xl p-6 border border-violet-100">
              <div className="w-12 h-12 rounded-xl bg-violet-500 flex items-center justify-center mb-4">
                <Zap className="w-6 h-6 text-white" />
              </div>
              <h3 className="font-bold text-violet-900 text-lg mb-2">Performance</h3>
              <p className="text-violet-700 text-sm">Carregamento rapido e otimizado</p>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-amber-200/50 bg-white/50 py-8 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="flex items-center justify-center gap-2 mb-3">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white font-bold text-sm">
              V
            </div>
            <span className="font-bold text-zinc-900">Vitrine11</span>
          </div>
          <p className="text-sm text-zinc-500">
            E-commerce Premium - Todos os direitos reservados
          </p>
        </div>
      </footer>
    </div>
  )
}

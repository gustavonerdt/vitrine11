export default function Home() {
  return (
    <div className="min-h-screen bg-[#fef9e0] flex flex-col items-center justify-center p-8">
      <div className="text-center max-w-2xl">
        <h1 className="text-4xl font-bold text-gray-900 mb-4">
          Vitrine E-commerce
        </h1>
        <p className="text-lg text-gray-600 mb-8">
          Este projeto e-commerce PHP foi configurado com sucesso. 
          Para visualizar a loja completa, faça o deploy em um servidor PHP com MySQL.
        </p>
        
        <div className="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Funil de Compra Configurado:</h2>
          <ol className="text-left space-y-3">
            <li className="flex items-center gap-3">
              <span className="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-sm">1</span>
              <span className="text-gray-700">index.php - Pagina inicial com produtos</span>
            </li>
            <li className="flex items-center gap-3">
              <span className="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-sm">2</span>
              <span className="text-gray-700">product.php - Detalhes do produto</span>
            </li>
            <li className="flex items-center gap-3">
              <span className="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-sm">3</span>
              <span className="text-gray-700">carrinho.php - Carrinho de compras</span>
            </li>
            <li className="flex items-center gap-3">
              <span className="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-sm">4</span>
              <span className="text-gray-700">checkout-entrega.php - Dados de entrega</span>
            </li>
            <li className="flex items-center gap-3">
              <span className="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-sm">5</span>
              <span className="text-gray-700">checkout-pagamento.php - Pagamento MP</span>
            </li>
            <li className="flex items-center gap-3">
              <span className="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-sm">6</span>
              <span className="text-gray-700">obrigado.php - Confirmacao do pedido</span>
            </li>
          </ol>
        </div>

        <div className="mt-8 grid grid-cols-2 gap-4 text-sm">
          <div className="bg-blue-50 p-4 rounded-xl">
            <div className="font-bold text-blue-900">API Mercado Pago</div>
            <div className="text-blue-700">PIX + Cartao de Credito</div>
          </div>
          <div className="bg-orange-50 p-4 rounded-xl">
            <div className="font-bold text-orange-900">API Correios</div>
            <div className="text-orange-700">Calculo de frete automatico</div>
          </div>
        </div>
      </div>
    </div>
  )
}

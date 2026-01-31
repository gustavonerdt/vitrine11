// music-player.js
// Player global de música de fundo com persistência SPA
// Padrão Universal: Autoplay mutado + desmutar na primeira interação

// Verificar se o elemento de áudio existe (música pode estar desativada)
const audioElementExists = document.getElementById('backgroundMusic') !== null;

if (!audioElementExists) {
    console.log('ℹ️ Player de música não encontrado - funcionalidade pode estar desativada');
    // Criar funções vazias para evitar erros
    window.loadPage = function(url) { window.location.href = url; };
    window.startMusic = function() {};
    window.rebindEvents = function() {};
    window.initializeMusicPlayer = function() {};
} else {
    // Log inicial simples
    console.log('🎵 Music Player carregado');

    // Variáveis globais
    let audio = null;
    let isInitialized = false;
    let hasUnmuted = false; // Flag para controlar se já desmutou

// Configurações
const MAX_VOLUME = 0.5; // Volume máximo

// Chave para sessionStorage
const MUSIC_STATE_KEY = 'musicPlayerState';

// Função para salvar estado da música
function saveMusicState() {
    try {
        sessionStorage.setItem(MUSIC_STATE_KEY, JSON.stringify({
            isPlaying: audio && !audio.paused,
            hasUnmuted: hasUnmuted,
            volume: audio ? audio.volume : MAX_VOLUME,
            timestamp: Date.now()
        }));
    } catch (e) {
        console.log('Não foi possível salvar estado da música:', e);
    }
}

// Função para carregar estado da música
function loadMusicState() {
    try {
        const state = sessionStorage.getItem(MUSIC_STATE_KEY);
        if (state) {
            return JSON.parse(state);
        }
    } catch (e) {
        console.log('Não foi possível carregar estado da música:', e);
    }
    return null;
}

// Função para limpar estado da música (quando necessário)
function clearMusicState() {
    try {
        sessionStorage.removeItem(MUSIC_STATE_KEY);
    } catch (e) {
        console.log('Não foi possível limpar estado da música:', e);
    }
}

// Função de log simplificada (apenas erros importantes)
function logMusicPlayer(level, message, data = null) {
    // Apenas logar erros e sucessos importantes
    if (level === 'ERROR' || level === 'SUCCESS') {
        const styles = {
            'ERROR': 'color: #F44336; font-weight: bold;',
            'SUCCESS': 'color: #4CAF50; font-weight: bold;'
        };
        console.log(`%c[${level}] ${message}`, styles[level] || '');
        if (data) {
            console.log(data);
        }
    }
}

// Função simplificada para diagnosticar estado do áudio (apenas em caso de erro)
function diagnoseAudioState() {
    if (!audio) {
        console.error('❌ Áudio não encontrado');
        return;
    }
    
    if (audio.error) {
        console.error('❌ Erro no áudio:', {
            code: audio.error.code,
            message: audio.error.message
        });
    }
    
    console.log('📊 Estado do áudio:', {
        paused: audio.paused,
        muted: audio.muted,
        volume: audio.volume,
        readyState: audio.readyState,
        networkState: audio.networkState
    });
}

// Inicializar player global (executa apenas uma vez)
function initializeMusicPlayer() {
    console.log('🎵 Inicializando player de música...');
    
    // Evitar múltiplas inicializações
    if (isInitialized) {
        console.log('ℹ️ Player já inicializado');
        return;
    }
    
    // Buscar elemento de áudio
    audio = document.getElementById('backgroundMusic');
    
    if (!audio) {
        console.error('❌ Elemento <audio> não encontrado');
        setTimeout(initializeMusicPlayer, 100);
        return;
    }
    
    console.log('✅ Elemento <audio> encontrado');
    
    // Configurar áudio
    audio.loop = true; // Loop infinito
    audio.preload = 'auto';
    audio.muted = true; // Mutado para autoplay funcionar
    audio.volume = MAX_VOLUME; // Volume máximo (mas mutado)
    
    // Prevenir pausa automática
    audio.addEventListener('pause', function(e) {
        // Se não foi pausado manualmente pelo usuário, continuar tocando do início
        if (hasUnmuted && !audio.muted) {
            console.log('⚠️ Tentativa de pausar música detectada - continuando do início...');
            setTimeout(() => {
                if (audio && hasUnmuted) {
                    audio.currentTime = 0;
                    audio.play().catch(err => {
                        console.log('Erro ao retomar música:', err);
                    });
                }
            }, 100);
        }
    });
    
    // Garantir que continue tocando quando terminar (loop infinito)
    audio.addEventListener('ended', function() {
        if (hasUnmuted) {
            console.log('🔄 Música terminou - reiniciando loop...');
            audio.currentTime = 0;
            audio.play().catch(err => {
                console.log('Erro ao reiniciar música:', err);
            });
        }
    });
    
    console.log('✅ Áudio configurado', {
        muted: audio.muted,
        volume: audio.volume,
        loop: audio.loop
    });
    
    // Verificar se a música estava tocando antes (persistência entre páginas)
    const savedState = loadMusicState();
    if (savedState && savedState.hasUnmuted) {
        console.log('🔄 Restaurando estado da música da sessão anterior...');
        hasUnmuted = true;
        audio.muted = false;
        audio.volume = savedState.volume || MAX_VOLUME;
        
        // SEMPRE começar do início quando restaurar
        audio.currentTime = 0;
        
        // Tentar continuar tocando do início
        if (savedState.isPlaying) {
            audio.play()
                .then(() => {
                    console.log('✅ Música continuando da sessão anterior (do início)!');
                    saveMusicState();
                })
                .catch(err => {
                    console.log('ℹ️ Aguardando interação para continuar música:', err.message);
                });
        }
    } else {
        // Tentar iniciar autoplay mutado apenas se não havia estado salvo
        if (audio.paused) {
            console.log('▶️ Tentando autoplay mutado...');
            audio.play()
                .then(() => {
                    console.log('✅ AUTOPLAY MUTADO FUNCIONOU!', {
                        paused: audio.paused,
                        currentTime: audio.currentTime
                    });
                })
                .catch(err => {
                    console.log('ℹ️ Autoplay mutado bloqueado (normal) - aguardando interação:', err.message);
                });
        }
    }
    
    isInitialized = true;
    console.log('✅ Player inicializado');
}

function startMusicDirect() {
    console.log('🎵 Iniciando música diretamente (sem fade-in)...');
    
    if (!audio) {
        console.error('❌ Áudio não inicializado');
        return;
    }
    
    if (hasUnmuted) {
        console.log('ℹ️ Música já foi desmutada');
        return;
    }
    
    // Desmutar e tocar direto
    audio.muted = false;
    audio.volume = MAX_VOLUME;
    hasUnmuted = true;
    
    // Garantir loop infinito
    audio.loop = true;
    
    // SEMPRE começar do início
    audio.currentTime = 0;
    
    console.log('✅ Áudio desmutado e volume configurado', {
        muted: audio.muted,
        volume: audio.volume,
        loop: audio.loop,
        currentTime: audio.currentTime
    });
    
    // Garantir que está tocando
    if (audio.paused) {
        console.log('▶️ Iniciando reprodução do início...');
        const playPromise = audio.play();
        
        if (playPromise !== undefined) {
            playPromise
                .then(() => {
                    console.log('✅ MÚSICA TOCANDO DO INÍCIO!', {
                        paused: audio.paused,
                        currentTime: audio.currentTime,
                        volume: audio.volume,
                        loop: audio.loop
                    });
                    // Salvar estado para persistir entre páginas
                    saveMusicState();
                })
                .catch(err => {
                    console.error('❌ Erro ao iniciar música:', err);
                    diagnoseAudioState();
                });
        }
    } else {
        // Se já estava tocando, reiniciar do início
        console.log('🔄 Reiniciando música do início...');
        audio.currentTime = 0;
        // Salvar estado mesmo se já estava tocando
        saveMusicState();
    }
}

function startMusic() {
    console.log('🎵 startMusic() chamada');
    
    // Verificar se já desmutou
    if (hasUnmuted) {
        console.log('ℹ️ Música já foi desmutada');
        return;
    }
    
    // Garantir que o player está inicializado
    if (!isInitialized) {
        console.log('⚠️ Player não inicializado - inicializando agora...');
        initializeMusicPlayer();
    }
    
    if (!audio) {
        console.error('❌ ÁUDIO NÃO DISPONÍVEL');
        return;
    }

    // Desmutar e tocar direto (sem fade-in)
    console.log('✅ PRIMEIRA INTERAÇÃO - Desmutando e tocando música...');
    startMusicDirect();
    
    // Remove os listeners de interação após o sucesso
    document.removeEventListener('click', handleUserInteraction);
    document.removeEventListener('keydown', handleUserInteraction);
    document.removeEventListener('touchstart', handleUserInteraction);
    document.removeEventListener('mousedown', handleUserInteraction);
}

// Função unificada para lidar com qualquer interação do usuário
// CRÍTICO: play() DEVE ser chamado DIRETAMENTE e SINCRONAMENTE no handler
function handleUserInteraction(event) {
    // Ignorar se já desmutou
    if (hasUnmuted) {
        return;
    }
    
    // IMPORTANTE: keydown não é considerado "interação válida" para autoplay em muitos navegadores
    // Ignorar keydown e focar apenas em eventos de mouse/touch
    if (event.type === 'keydown') {
        console.log('ℹ️ keydown ignorado - use click/touch para iniciar música');
        return;
    }
    
    // Garantir que o player está inicializado
    if (!isInitialized || !audio) {
        // Se não tem audio, tentar buscar agora
        if (!audio) {
            audio = document.getElementById('backgroundMusic');
        }
        
        if (!audio) {
            // Se ainda não tem, inicializar
            initializeMusicPlayer();
            // Se ainda não tem após inicializar, sair
            if (!audio) {
                console.error('❌ Áudio não disponível');
                return;
            }
        }
    }
    
    console.log('🖱️ INTERAÇÃO VÁLIDA DETECTADA!', event.type);
    
    // CRÍTICO: Tudo deve acontecer SINCRONAMENTE no handler do evento
    // 1. Desmutar
    audio.muted = false;
    audio.volume = MAX_VOLUME;
    hasUnmuted = true;
    
    // Garantir loop infinito
    audio.loop = true;
    
    // SEMPRE começar do início
    audio.currentTime = 0;
    
    console.log('🎵 Desmutado - chamando play() DIRETAMENTE do início...');
    
    // 2. Chamar play() IMEDIATAMENTE no mesmo stack frame do evento
    // O navegador só aceita se for chamado diretamente no handler
    const playPromise = audio.play();
    
    if (playPromise !== undefined) {
        playPromise
            .then(() => {
                console.log('✅ MÚSICA TOCANDO!', {
                    paused: audio.paused,
                    currentTime: audio.currentTime,
                    volume: audio.volume,
                    loop: audio.loop
                });
                
                // Salvar estado para persistir entre páginas
                saveMusicState();
                
                // Remover todos os listeners após sucesso
                removeAllListeners();
            })
            .catch(err => {
                console.error('❌ Erro ao iniciar música:', err.name);
                console.log('💡 Dica: Clique diretamente na página (não use teclado)');
                
                // Se falhar, pode ser que o evento não seja válido
                // Tentar criar um evento de click programático como último recurso
                if (event.type !== 'click') {
                    console.log('🔄 Tentando com evento click programático...');
                    // Criar um elemento invisível e clicar nele
                    const trigger = document.createElement('div');
                    trigger.style.cssText = 'position: fixed; top: 0; left: 0; width: 1px; height: 1px; opacity: 0; pointer-events: none;';
                    document.body.appendChild(trigger);
                    
                    trigger.addEventListener('click', function triggerClick() {
                        trigger.removeEventListener('click', triggerClick);
                        audio.currentTime = 0;
                        audio.play()
                            .then(() => {
                                console.log('✅ Música iniciada via click programático (do início)!');
                                trigger.remove();
                                removeAllListeners();
                            })
                            .catch(e => {
                                console.error('❌ Falhou:', e);
                                trigger.remove();
                            });
                    }, { once: true });
                    
                    trigger.click();
                }
            });
    }
}

// Função auxiliar para remover todos os listeners
function removeAllListeners() {
    document.removeEventListener('click', handleUserInteraction);
    document.removeEventListener('touchstart', handleUserInteraction);
    document.removeEventListener('mousedown', handleUserInteraction);
    document.removeEventListener('pointerdown', handleUserInteraction);
    document.removeEventListener('keydown', handleUserInteraction);
}

// --- Lógica de Navegação SPA ---
// Garantir que o player não seja afetado durante navegação

// Função para carregar o conteúdo da página via AJAX
function loadPage(url, pushState = true) {
    console.log('Carregando página via AJAX:', url);
    
    // IMPORTANTE: Preservar estado do player antes de carregar nova página
    const audioState = {
        currentTime: audio ? audio.currentTime : 0,
        volume: audio ? audio.volume : 0,
        paused: audio ? audio.paused : true
    };
    
    // 1. Ocultar o conteúdo atual (opcional: adicionar um spinner de carregamento)
    const mainContent = document.querySelector('.public-main-content');
    if (mainContent) {
        mainContent.style.opacity = 0.5; // Efeito de carregamento
    }

    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // Indica que é uma requisição AJAX
        }
    })
    .then(response => {
        // Se a resposta não for um fragmento (ex: erro, redirecionamento para login), faz o reload completo
        if (response.headers.get('Content-Type') && !response.headers.get('Content-Type').includes('text/html')) {
             window.location.href = url;
             return;
        }
        return response.text();
    })
    .then(html => {
        if (!html) return;

        // 2. Inserir o novo conteúdo
        if (mainContent) {
            // Extrai apenas o conteúdo dentro da div .public-main-content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newContent = tempDiv.querySelector('.public-main-content')?.innerHTML || html;
            
            mainContent.innerHTML = newContent;
            mainContent.style.opacity = 1; // Remover efeito de carregamento
        }

        // 3. Atualizar o estado do histórico e o título da página
        if (pushState) {
            history.pushState({ path: url }, '', url);
        }
        
        // Tenta extrair o título do novo conteúdo
        const newTitleMatch = html.match(/<title>(.*?)<\/title>/i);
        if (newTitleMatch && newTitleMatch[1]) {
            document.title = newTitleMatch[1];
        } else {
            // Fallback: tenta extrair de um elemento específico ou usa um fallback
            const h1 = mainContent ? mainContent.querySelector('h1') : null;
            document.title = (h1 ? h1.textContent + ' - ' : '') + 'Vitrine Independente';
        }

        // 4. Re-executar scripts (se houver) e re-bindar eventos
        rebindEvents();
        
        // 5. Scroll para o topo
        window.scrollTo(0, 0);
        
        // 6. IMPORTANTE: Restaurar estado do player (se ainda existir)
        // O elemento <audio> não deve ser recriado, mas verificamos para garantir
        if (audio && audioState) {
            // Verificar se o player ainda existe no DOM
            const audioElement = document.getElementById('backgroundMusic');
            if (audioElement && audioElement === audio) {
                // Player ainda existe, restaurar estado se necessário
                // Garantir loop infinito
                audio.loop = true;
                
                // Se estava tocando antes, continuar tocando do início
                if (!audioState.paused && audio.paused) {
                    audio.currentTime = 0;
                    audio.play().catch(err => {
                        console.log('Não foi possível retomar música após navegação:', err);
                    });
                } else if (!audio.paused) {
                    // Se já está tocando, garantir que está do início
                    audio.currentTime = 0;
                }
                
                // Salvar estado atualizado
                saveMusicState();
            } else {
                // Player foi recriado (não deveria acontecer, mas se acontecer, reinicializar)
                console.warn('Player de áudio foi recriado durante navegação. Reinicializando...');
                isInitialized = false;
                initializeMusicPlayer();
                if (!audioState.paused) {
                    startMusic();
                }
            }
        }

    })
    .catch(error => {
        console.error('Erro ao carregar a página via AJAX:', error);
        // Em caso de erro, faz o reload completo como fallback
        window.location.href = url;
    });
}

// Função para re-bindar eventos (links)
function rebindEvents() {
    // Remove listeners antigos para evitar duplicação
    document.querySelectorAll('a[data-spa-link]').forEach(link => {
        link.removeEventListener('click', handleLinkClick);
    });

    // Adiciona o atributo data-spa-link a todos os links internos que não são para download, admin, ou externos
    document.querySelectorAll('a').forEach(link => {
        const href = link.getAttribute('href');
        // Verifica se é um link interno (mesmo host), não é âncora, não é admin, não é logout, e não tem download
        if (href && !href.startsWith('#') && !href.startsWith('mailto:') && !href.startsWith('tel:') && !href.startsWith('javascript:') && !href.includes('/admin') && !href.includes('logout') && !link.hasAttribute('download')) {
            // Verificar se é mesmo host (para links relativos e absolutos)
            try {
                const linkUrl = new URL(href, window.location.href);
                if (linkUrl.host === window.location.host) {
                    link.setAttribute('data-spa-link', 'true');
                } else {
                    link.removeAttribute('data-spa-link');
                }
            } catch (e) {
                // Se for link relativo, considerar como interno
                if (!href.startsWith('http')) {
                    link.setAttribute('data-spa-link', 'true');
                } else {
                    link.removeAttribute('data-spa-link');
                }
            }
        } else {
            link.removeAttribute('data-spa-link');
        }
    });

    // Adiciona o novo listener
    document.querySelectorAll('a[data-spa-link]').forEach(link => {
        link.addEventListener('click', handleLinkClick);
    });
}

function handleLinkClick(e) {
    const url = this.getAttribute('href');
    // Previne o comportamento padrão (navegação completa)
    e.preventDefault();
    loadPage(url);
}

// Lidar com o botão de voltar/avançar do navegador
window.addEventListener('popstate', (e) => {
    if (e.state && e.state.path) {
        loadPage(e.state.path, false); // Não adiciona ao histórico novamente
    } else {
        // Se não houver estado, faz um reload completo (fallback)
        window.location.reload();
    }
});

// Função auxiliar para verificar se o autoplay mutado está funcionando
function verifyAutoplay() {
    if (!audio) return;
    
    if (audio.paused && !hasUnmuted) {
        console.log('▶️ Tentando autoplay mutado novamente...');
        audio.play().catch(() => {
            // Ignorar - será iniciado na interação
        });
    } else if (!audio.paused) {
        console.log('✅ Música já está tocando (mutada)');
    }
}

// Função de inicialização completa
function initializePage() {
    console.log('🚀 Inicializando página...');
    
    // Verificar se há estado salvo da música
    const savedState = loadMusicState();
    if (savedState && savedState.hasUnmuted) {
        console.log('🔄 Estado de música encontrado - restaurando...');
        // Marcar como desmutado antes de inicializar
        hasUnmuted = true;
    }
    
    // Inicializar player de música
    initializeMusicPlayer();
    
    // Inicializa a ligação dos eventos de navegação
    rebindEvents();
    
    // Verificar autoplay após delay
    setTimeout(() => {
        verifyAutoplay();
        
        // Se havia estado salvo e música estava tocando, garantir que continue do início
        if (savedState && savedState.hasUnmuted && savedState.isPlaying) {
            if (audio && audio.paused) {
                console.log('🔄 Retomando música da sessão anterior (do início)...');
                audio.currentTime = 0;
                audio.play().catch(err => {
                    console.log('Erro ao retomar música:', err);
                });
            } else if (audio && !audio.paused) {
                // Se já está tocando, garantir que está do início
                audio.currentTime = 0;
            }
        }
    }, 500);
}

// Inicialização quando DOM está pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage);
} else {
    initializePage();
}

// Fallback - tentar novamente após 1 segundo se não inicializou
setTimeout(() => {
    if (!isInitialized) {
        initializePage();
    }
}, 1000);

// Verificar autoplay quando página carregar
window.addEventListener('load', () => {
    if (!hasUnmuted) {
        verifyAutoplay();
    }
    
    // Verificar periodicamente se a música parou e continuar se necessário
    setInterval(() => {
        if (audio && hasUnmuted && !audio.muted && audio.paused) {
            console.log('🔄 Música pausada detectada - continuando do início...');
            audio.currentTime = 0;
            audio.play().catch(err => {
                console.log('Erro ao retomar música:', err);
            });
        }
        
        // Atualizar estado salvo periodicamente
        if (audio && hasUnmuted) {
            saveMusicState();
        }
    }, 2000); // Verificar a cada 2 segundos
});

// Salvar estado antes de sair da página
window.addEventListener('beforeunload', () => {
    if (audio && hasUnmuted) {
        saveMusicState();
    }
});

// Salvar estado quando a página fica oculta (tab muda)
document.addEventListener('visibilitychange', () => {
    if (audio && hasUnmuted) {
        saveMusicState();
    }
});

// Criar overlay invisível que captura o primeiro clique
function createInteractionOverlay() {
    // Se já existe, não criar novamente
    if (document.getElementById('music-player-overlay')) {
        return;
    }
    
    const overlay = document.createElement('div');
    overlay.id = 'music-player-overlay';
    overlay.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 2147483646 !important;
        background: transparent !important;
        pointer-events: none !important;
    `;
    
    document.body.appendChild(overlay);
    
    // Handler direto no overlay - APENAS para click e mousedown (eventos válidos)
    function overlayClick(e) {
        // IMPORTANTE: Apenas aceitar click e mousedown (eventos mais confiáveis)
        if (e.type !== 'click' && e.type !== 'mousedown') {
            console.log('ℹ️ Evento ignorado (não é click/mousedown):', e.type);
            return;
        }
        
        console.log('🖱️ CLIQUE VÁLIDO DETECTADO!', e.type);
        
        // Prevenir propagação
        e.preventDefault();
        e.stopPropagation();
        
        if (hasUnmuted) {
            console.log('ℹ️ Já desmutado, removendo overlay');
            overlay.remove();
            return;
        }
        
        // Garantir que o player está inicializado
        if (!isInitialized || !audio) {
            if (!audio) {
                audio = document.getElementById('backgroundMusic');
            }
            if (!audio) {
                initializeMusicPlayer();
                if (!audio) {
                    console.error('❌ Áudio não disponível');
                    return;
                }
            }
        }
        
        console.log('🎵 Desmutando e iniciando música...');
        
        // CRÍTICO: Desmutar e chamar play() SINCRONAMENTE no handler
        audio.muted = false;
        audio.volume = MAX_VOLUME;
        hasUnmuted = true;
        
        // Garantir loop infinito
        audio.loop = true;
        
        // SEMPRE começar do início
        audio.currentTime = 0;
        
        // Chamar play() IMEDIATAMENTE (sem try/catch que possa atrasar)
        const playPromise = audio.play();
        
        if (playPromise !== undefined) {
            playPromise
                .then(() => {
                    console.log('✅ MÚSICA TOCANDO!');
                    // Salvar estado para persistir entre páginas
                    saveMusicState();
                    overlay.remove();
                    removeAllListeners();
                })
                .catch(err => {
                    console.error('❌ Erro:', err.name);
                    // Se falhar, o navegador está bloqueando mesmo com interação válida
                    console.log('💡 Navegador bloqueou autoplay mesmo com click válido');
                    console.log('💡 Solução: Use um botão visível para iniciar música');
                });
        }
    }
    
    // APENAS click e mousedown (eventos mais confiáveis para autoplay)
    overlay.addEventListener('click', overlayClick, { once: true, capture: true, passive: false });
    overlay.addEventListener('mousedown', overlayClick, { once: true, capture: true, passive: false });
    
    // Também adicionar no window como fallback
    window.addEventListener('click', function windowClick(e) {
        if (!hasUnmuted) {
            console.log('🖱️ Clique capturado via window');
            overlayClick(e);
        }
    }, { once: true, capture: true });
    
    // Garantir que o overlay está no topo e visível
    document.body.appendChild(overlay);
    
    // Forçar atualização do z-index
    setTimeout(() => {
        overlay.style.zIndex = '2147483647';
    }, 0);
    
    console.log('👂 Overlay criado');
    console.log('💡 Clique em qualquer lugar da página para iniciar música');
    console.log('📍 Overlay posicionado:', {
        zIndex: window.getComputedStyle(overlay).zIndex,
        width: overlay.offsetWidth,
        height: overlay.offsetHeight
    });
}

// Função para iniciar música diretamente (chamada no contexto do evento)
function startMusicDirectly() {
    if (hasUnmuted || !audio) return;
    
    console.log('🎵 Iniciando música diretamente...');
    
    audio.muted = false;
    audio.volume = MAX_VOLUME;
    hasUnmuted = true;
    
    // Garantir loop infinito
    audio.loop = true;
    
    // SEMPRE começar do início
    audio.currentTime = 0;
    
    const playPromise = audio.play();
    
    if (playPromise !== undefined) {
        playPromise
            .then(() => {
                console.log('✅ MÚSICA TOCANDO!');
                // Salvar estado para persistir entre páginas
                saveMusicState();
                removeAllListeners();
                const overlay = document.getElementById('music-player-overlay');
                if (overlay) overlay.remove();
            })
            .catch(err => {
                console.error('❌ Erro:', err.name);
            });
    }
}

// Adiciona listeners para a primeira interação do usuário
function attachInteractionListeners() {
    // Remover listeners antigos
    removeAllListeners();
    
    // Criar overlay invisível
    createInteractionOverlay();
    
    // Listener direto no body
    function bodyClickHandler(e) {
        // Apenas aceitar click e mousedown
        if (e.type !== 'click' && e.type !== 'mousedown') {
            return;
        }
        
        if (hasUnmuted) {
            document.body.removeEventListener('click', bodyClickHandler);
            document.body.removeEventListener('mousedown', bodyClickHandler);
            return;
        }
        
        // Garantir que audio está disponível
        if (!audio) {
            audio = document.getElementById('backgroundMusic');
        }
        
        if (!audio) {
            initializeMusicPlayer();
            if (!audio) return;
        }
        
        // Desmutar e tocar DIRETAMENTE no handler
        audio.muted = false;
        audio.volume = MAX_VOLUME;
        hasUnmuted = true;
        
        // Garantir loop infinito
        audio.loop = true;
        
        // SEMPRE começar do início
        audio.currentTime = 0;
        
        // Chamar play() DIRETAMENTE no handler
        audio.play()
            .then(() => {
                console.log('✅ MÚSICA TOCANDO!');
                // Salvar estado para persistir entre páginas
                saveMusicState();
                document.body.removeEventListener('click', bodyClickHandler);
                document.body.removeEventListener('mousedown', bodyClickHandler);
                removeAllListeners();
                const overlay = document.getElementById('music-player-overlay');
                if (overlay) overlay.remove();
            })
            .catch(err => {
                console.error('❌ Erro:', err.name);
            });
    }
    
    // Adicionar listeners no body - APENAS click e mousedown
    document.body.addEventListener('click', bodyClickHandler, { once: false, capture: true, passive: false });
    document.body.addEventListener('mousedown', bodyClickHandler, { once: false, capture: true, passive: false });
}

    // Anexar listeners imediatamente
    attachInteractionListeners();

    // Exportar funções para serem usadas em outros scripts se necessário
    window.loadPage = loadPage;
    window.startMusic = startMusic;
    window.rebindEvents = rebindEvents;
    window.initializeMusicPlayer = initializeMusicPlayer;
}

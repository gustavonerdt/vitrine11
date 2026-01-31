// ============================================
// MUSIC PLAYER SIMPLES - FUNCIONA 100%
// ============================================
// Este player é SIMPLES e DIRETO
// Toca automaticamente e persiste entre páginas

(function() {
    'use strict';

    console.log('🎵 MUSIC PLAYER SIMPLES CARREGADO');

    // ============================================
    // CRIAR ELEMENTO DE ÁUDIO GLOBAL
    // ============================================
    function createAudioElement() {
        // Se já existe, retorna
        if (window.globalAudio) {
            console.log('✅ Áudio global já existe');
            return window.globalAudio;
        }

        console.log('📻 Criando elemento de áudio global...');

        const audio = new Audio();
        audio.id = 'globalBackgroundMusic';
        audio.loop = true;
        audio.preload = 'auto';
        audio.crossOrigin = 'anonymous';
        
        // Definir URL
        if (window.MUSIC_URL) {
            audio.src = window.MUSIC_URL;
            console.log('🎵 URL da música:', window.MUSIC_URL);
        }

        // Salvar globalmente
        window.globalAudio = audio;
        
        // Adicionar ao DOM (oculto)
        if (!document.body.contains(audio)) {
            document.body.appendChild(audio);
        }

        console.log('✅ Elemento de áudio criado');
        return audio;
    }

    // ============================================
    // INICIAR MÚSICA
    // ============================================
    function startMusic() {
        const audio = createAudioElement();

        if (audio.paused) {
            console.log('▶️ Iniciando música...');
            
            const playPromise = audio.play();
            
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        console.log('✅ MÚSICA TOCANDO!');
                        saveState();
                    })
                    .catch(error => {
                        console.log('❌ Erro ao tocar:', error.message);
                        // Tentar novamente em 1 segundo
                        setTimeout(startMusic, 1000);
                    });
            }
        } else {
            console.log('ℹ️ Música já está tocando');
        }
    }

    // ============================================
    // PAUSAR MÚSICA
    // ============================================
    function pauseMusic() {
        const audio = createAudioElement();
        if (!audio.paused) {
            console.log('⏸️ Pausando música...');
            audio.pause();
            saveState();
        }
    }

    // ============================================
    // RETOMAR MÚSICA
    // ============================================
    function resumeMusic() {
        startMusic();
    }

    // ============================================
    // SALVAR ESTADO
    // ============================================
    function saveState() {
        try {
            const audio = window.globalAudio;
            if (!audio) return;

            const state = {
                isPlaying: !audio.paused,
                currentTime: audio.currentTime,
                timestamp: Date.now()
            };

            localStorage.setItem('musicPlayerState', JSON.stringify(state));
            console.log('💾 Estado salvo:', {
                playing: state.isPlaying,
                time: state.currentTime.toFixed(2)
            });
        } catch (e) {
            console.log('⚠️ Erro ao salvar:', e.message);
        }
    }

    // ============================================
    // CARREGAR ESTADO
    // ============================================
    function loadState() {
        try {
            const state = JSON.parse(localStorage.getItem('musicPlayerState'));
            if (!state) return null;

            console.log('📂 Estado carregado:', {
                playing: state.isPlaying,
                time: state.currentTime.toFixed(2)
            });

            return state;
        } catch (e) {
            console.log('⚠️ Erro ao carregar:', e.message);
            return null;
        }
    }

    // ============================================
    // RESTAURAR POSIÇÃO DA MÚSICA
    // ============================================
    function restorePosition() {
        const state = loadState();
        if (!state) return;

        const audio = createAudioElement();
        
        // Calcular quanto tempo passou
        const timePassed = (Date.now() - state.timestamp) / 1000;
        let newTime = state.currentTime + timePassed;

        // Se passou do final, volta ao início
        if (newTime >= audio.duration) {
            newTime = 0;
        }

        console.log('🔄 Restaurando posição:', newTime.toFixed(2) + 's');
        audio.currentTime = newTime;

        // Se estava tocando, continuar tocando
        if (state.isPlaying) {
            startMusic();
        }
    }

    // ============================================
    // SALVAR ESTADO AO SAIR
    // ============================================
    window.addEventListener('beforeunload', saveState);
    window.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            saveState();
        }
    });

    // ============================================
    // HANDLER DE INTERAÇÃO DO USUÁRIO
    // ============================================
    function handleUserInteraction() {
        console.log('🖱️ Interação do usuário detectada');
        startMusic();
        
        // Remover listeners após primeira interação
        document.removeEventListener('click', handleUserInteraction);
        document.removeEventListener('touchstart', handleUserInteraction);
    }

    // ============================================
    // INICIALIZAR
    // ============================================
    function initialize() {
        console.log('🚀 Inicializando Music Player Simples...');

        // Criar elemento de áudio
        createAudioElement();

        // Restaurar estado anterior
        restorePosition();

        // Adicionar listeners de interação
        document.addEventListener('click', handleUserInteraction, { once: false });
        document.addEventListener('touchstart', handleUserInteraction, { once: false });

        // Tentar autoplay após 1 segundo
        setTimeout(() => {
            console.log('⏱️ Tentando autoplay...');
            startMusic();
        }, 1000);

        console.log('✅ Music Player Simples inicializado');
    }

    // ============================================
    // EXECUTAR QUANDO DOM ESTIVER PRONTO
    // ============================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // ============================================
    // EXPORTAR API GLOBAL
    // ============================================
    window.musicPlayer = {
        start: startMusic,
        pause: pauseMusic,
        resume: resumeMusic,
        getAudio: () => window.globalAudio
    };

    console.log('✅ API disponível em window.musicPlayer');
})();

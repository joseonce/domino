/**
 * juego.js - Controlador Front-End Accesible para Dominó Español
 * Compatible con lectores de pantalla (NVDA, VoiceOver) y WCAG 2.1 AA.
 */

(function () {
    'use strict';

    // Estado local de la aplicación
    const estado = {
        codigo: '',
        token: '',
        esAnfitrion: false,
        partida: null,
        jugadores: [],
        miJugador: null,
        miMano: [],
        ultimaManoJSON: '',
        ultimoMovimientoRegistrado: '',
        ultimoComentarioIARegistrado: '',
        ultimoTurnoAnunciadoId: null,
        eraMiTurnoPreviamente: false,
        pantallaActual: '',
        anuncioInicialRealizado: false,
        pollingTimer: null,
        fichaSeleccionadaParaExtremo: null,
        audioActivo: true
    };

    // Elementos del DOM
    const DOM = {
        polite: document.getElementById('anuncio-polite'),
        assertive: document.getElementById('anuncio-assertive'),
        badgeModo: document.getElementById('badge-modo'),
        btnAltoContraste: document.getElementById('btn-alto-contraste'),
        btnAyudaAtajos: document.getElementById('btn-ayuda-atajos'),
        btnManual: document.getElementById('btn-manual'),
        
        // Pantallas
        pantallaInicio: document.getElementById('pantalla-inicio'),
        pantallaEspera: document.getElementById('pantalla-espera'),
        pantallaJuego: document.getElementById('pantalla-juego'),
        pantallaFin: document.getElementById('pantalla-fin'),

        // Formularios
        formCrear: document.getElementById('form-crear'),
        crearMaxJugadores: document.getElementById('crear-max-jugadores'),
        crearModo: document.getElementById('crear-modo'),
        grupoModo: document.getElementById('grupo-modo'),
        formUnirse: document.getElementById('form-unirse'),
        unirseCodigo: document.getElementById('unirse-codigo'),

        // Sala de espera
        esperaCodigo: document.getElementById('espera-codigo'),
        inputInvitacion: document.getElementById('input-enlace-invitacion'),
        btnCopiarEnlace: document.getElementById('btn-copiar-enlace'),
        esperaModoTexto: document.getElementById('espera-modo-texto'),
        esperaPlazasTexto: document.getElementById('espera-plazas-texto'),
        esperaCuenta: document.getElementById('espera-cuenta'),
        listaJugadoresEspera: document.getElementById('lista-jugadores-espera'),
        btnAgregarBot: document.getElementById('btn-agregar-bot'),
        btnIniciarPartida: document.getElementById('btn-iniciar-partida'),
        esperaMensajeEstado: document.getElementById('espera-mensaje-estado'),

        // Tablero y Juego
        cajaTurno: document.getElementById('caja-turno'),
        textoTurno: document.getElementById('texto-turno'),
        textoExtremoIzq: document.getElementById('texto-extremo-izq'),
        textoExtremoDer: document.getElementById('texto-extremo-der'),
        textoPozoCuenta: document.getElementById('texto-pozo-cuenta'),
        cajaComentarioIA: document.getElementById('caja-comentario-ia'),
        textoComentarioIA: document.getElementById('texto-comentario-ia'),
        contenedorRivales: document.getElementById('contenedor-rivales'),
        cuentaTablero: document.getElementById('cuenta-tablero'),
        fichasTablero: document.getElementById('fichas-tablero'),
        tableroScroll: document.getElementById('tablero-fichas-scroll'),
        cuentaMano: document.getElementById('cuenta-mano'),
        tituloMano: document.getElementById('titulo-mano'),
        contenedorMano: document.getElementById('contenedor-mano'),
        btnEstadoTablero: document.getElementById('btn-estado-tablero'),
        btnResumenJugadores: document.getElementById('btn-resumen-jugadores'),
        btnRobar: document.getElementById('btn-robar'),
        btnPasar: document.getElementById('btn-pasar'),
        btnAbandonarPartida: document.getElementById('btn-abandonar-partida'),
        registroMovimientos: document.getElementById('registro-movimientos'),

        // Modales
        modalExtremo: document.getElementById('modal-extremo'),
        btnExtremoIzq: document.getElementById('btn-extremo-izq'),
        btnExtremoDer: document.getElementById('btn-extremo-der'),
        numExtremoIzq: document.getElementById('num-extremo-izq'),
        numExtremoDer: document.getElementById('num-extremo-der'),
        btnCancelarExtremo: document.getElementById('btn-cancelar-extremo'),

        modalAtajos: document.getElementById('modal-atajos'),
        btnCerrarAtajos: document.getElementById('btn-cerrar-atajos'),

        // Fin de partida
        resultadoFinCaja: document.getElementById('resultado-fin-caja'),
        cuentaTableroFin: document.getElementById('cuenta-tablero-fin'),
        extremosTableroFin: document.getElementById('extremos-tablero-fin'),
        tableroFichasFin: document.getElementById('tablero-fichas-fin'),
        registroMovimientosFin: document.getElementById('registro-movimientos-fin'),
        btnNuevaPartida: document.getElementById('btn-nueva-partida')
    };

    // =========================================================================
    // UTILIDADES DE ACCESIBILIDAD Y COLA DE ANUNCIOS ARIA-LIVE POLITE
    // =========================================================================

    const colaAnuncios = [];
    let procesandoColaAnuncios = false;
    let colaVaciaCallback = null;
    let tiempoFinEstimadoAnuncios = 0;

    function anunciarPolite(mensaje) {
        if (!mensaje || typeof mensaje !== 'string') return;
        const textoLimpio = mensaje.trim();
        if (!textoLimpio) return;

        colaAnuncios.push(textoLimpio);
        procesarSiguienteAnuncio();
    }

    // Permite ejecutar una acción (como situar el foco en "Te quedan X fichas") justo después
    // de que el lector termine de anunciar las jugadas de los rivales previos (1, 2 o 3 jugadores).
    // Sin segundo de cortesía innecesario: se activa de inmediato en cuanto concluye la locución.
    function ejecutarAlVaciarCola(fn) {
        if (!procesandoColaAnuncios && colaAnuncios.length === 0) {
            const restante = Math.max(0, tiempoFinEstimadoAnuncios - Date.now());
            setTimeout(fn, restante + 50);
        } else {
            colaVaciaCallback = fn;
        }
    }

    // Emite anuncios prioritarios e inmediatos (añadir jugadores, errores, selecciones)
    function anunciarAssertive(mensaje) {
        if (!mensaje || typeof mensaje !== 'string') return;
        const textoLimpio = mensaje.trim();
        if (!textoLimpio) return;

        if (DOM.assertive) {
            DOM.assertive.textContent = '';
            setTimeout(() => {
                DOM.assertive.textContent = textoLimpio;
            }, 30);
        }
    }

    // Interrumpe inmediatamente la voz en curso (para confirmación de fichas jugadas)
    function interrumpirYAnunciar(mensaje) {
        if (!mensaje || typeof mensaje !== 'string') return;
        const textoLimpio = mensaje.trim();
        if (!textoLimpio) return;

        // 1. Vaciar cola de anuncios pendientes para silenciar lo anterior
        colaAnuncios.length = 0;
        procesandoColaAnuncios = false;
        colaVaciaCallback = null;
        tiempoFinEstimadoAnuncios = Date.now() + 450;

        // 2. Limpiar región polite inmediatamente
        if (DOM.polite) {
            DOM.polite.textContent = '';
        }

        // 3. Emitir por la región assertive para que el lector corte la lectura previa
        if (DOM.assertive) {
            DOM.assertive.textContent = '';
            setTimeout(() => {
                DOM.assertive.textContent = textoLimpio;
            }, 40);
        }
    }

    function procesarSiguienteAnuncio() {
        if (procesandoColaAnuncios) return;
        if (colaAnuncios.length === 0) {
            if (colaVaciaCallback) {
                const cb = colaVaciaCallback;
                colaVaciaCallback = null;
                // Ejecución inmediata al concluir la cola de anuncios
                setTimeout(cb, 50);
            }
            return;
        }
        procesandoColaAnuncios = true;

        const siguiente = colaAnuncios.shift();
        if (DOM.polite) {
            DOM.polite.textContent = '';
            setTimeout(() => {
                DOM.polite.textContent = siguiente;
                // Duración ajustada a la velocidad de habla normal en español
                const palabras = siguiente.split(/\s+/).filter(Boolean).length;
                const duracionEstimada = Math.max(palabras * 300, siguiente.length * 52);
                const esperaMs = Math.max(1200, Math.min(4500, duracionEstimada));

                tiempoFinEstimadoAnuncios = Date.now() + esperaMs;

                setTimeout(() => {
                    procesandoColaAnuncios = false;
                    procesarSiguienteAnuncio();
                }, esperaMs);
            }, 50);
        } else {
            procesandoColaAnuncios = false;
            procesarSiguienteAnuncio();
        }
    }

    function nombreNumero(n) {
        const nombres = ['blanca', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis'];
        const idx = Number(n);
        return (idx >= 0 && idx < nombres.length) ? nombres[idx] : String(n);
    }

    function nombrePalo(n) {
        if (n === null || n === undefined) return 'vacío';
        const num = Number(n);
        return (num === 0) ? 'blanca' : String(num);
    }

    function describirFichaCorta(f) {
        if (!f || f.length < 2) return '';
        const p1 = (Number(f[0]) === 0) ? 'blanca' : String(f[0]);
        const p2 = (Number(f[1]) === 0) ? 'blanca' : String(f[1]);
        return `${p1} ${p2}`;
    }

    function describirFicha(f) {
        if (!f || f.length < 2) return 'Ficha desconocida';
        const p1 = (Number(f[0]) === 0) ? 'blanca' : String(f[0]);
        const p2 = (Number(f[1]) === 0) ? 'blanca' : String(f[1]);
        return `Ficha ${p1} ${p2}`;
    }

    /**
     * Ordena las fichas de la mano siguiendo la estrategia tradicional del dominó:
     * 1. Dobles primero (frecuencia descendente, valor descendente).
     * 2. Acompañantes de dobles en mano (por frecuencia del doble más repetido).
     * 3. Números repetidos sin doble (palos fuertes de apoyo).
     * 4. Fichas huérfanas / no repetidas (reservadas para el final).
     */
    function ordenarManoEstrategica(listaFichas) {
        if (!Array.isArray(listaFichas) || listaFichas.length <= 1) return listaFichas;

        const conteo = [0, 0, 0, 0, 0, 0, 0];
        const tieneDoble = [false, false, false, false, false, false, false];

        listaFichas.forEach(item => {
            const f = Array.isArray(item) ? item : item.ficha;
            const a = Number(f[0]);
            const b = Number(f[1]);
            conteo[a]++;
            if (a !== b) {
                conteo[b]++;
            } else {
                tieneDoble[a] = true;
            }
        });

        const grupoDobles = [];
        const grupoAcompDobles = [];
        const grupoRepetidos = [];
        const grupoHuerfanos = [];

        listaFichas.forEach(item => {
            const f = Array.isArray(item) ? item : item.ficha;
            const a = Number(f[0]);
            const b = Number(f[1]);

            if (a === b) {
                grupoDobles.push({ item, paloDoble: a, frec: conteo[a] });
            } else if (tieneDoble[a] || tieneDoble[b]) {
                let paloDoble, otro;
                if (tieneDoble[a] && tieneDoble[b]) {
                    if (conteo[a] > conteo[b]) { paloDoble = a; otro = b; }
                    else if (conteo[b] > conteo[a]) { paloDoble = b; otro = a; }
                    else { paloDoble = Math.max(a, b); otro = Math.min(a, b); }
                } else if (tieneDoble[a]) {
                    paloDoble = a; otro = b;
                } else {
                    paloDoble = b; otro = a;
                }
                grupoAcompDobles.push({
                    item,
                    frecDoble: conteo[paloDoble],
                    paloDoble,
                    frecOtro: conteo[otro],
                    otro
                });
            } else if (Math.max(conteo[a], conteo[b]) >= 2) {
                let paloRep, otro;
                if (conteo[a] > conteo[b]) { paloRep = a; otro = b; }
                else if (conteo[b] > conteo[a]) { paloRep = b; otro = a; }
                else { paloRep = Math.max(a, b); otro = Math.min(a, b); }
                grupoRepetidos.push({
                    item,
                    frecRep: conteo[paloRep],
                    paloRep,
                    frecOtro: conteo[otro],
                    otro
                });
            } else {
                grupoHuerfanos.push({
                    item,
                    puntos: a + b,
                    maxVal: Math.max(a, b)
                });
            }
        });

        grupoDobles.sort((x, y) => (y.frec - x.frec) || (y.paloDoble - x.paloDoble));
        grupoAcompDobles.sort((x, y) => (y.frecDoble - x.frecDoble) || (y.paloDoble - x.paloDoble) || (y.frecOtro - x.frecOtro) || (y.otro - x.otro));
        grupoRepetidos.sort((x, y) => (y.frecRep - x.frecRep) || (y.paloRep - x.paloRep) || (y.frecOtro - x.frecOtro) || (y.otro - x.otro));
        grupoHuerfanos.sort((x, y) => (y.puntos - x.puntos) || (y.maxVal - x.maxVal));

        const res = [];
        grupoDobles.forEach(x => res.push(x.item));
        grupoAcompDobles.forEach(x => res.push(x.item));
        grupoRepetidos.forEach(x => res.push(x.item));
        grupoHuerfanos.forEach(x => res.push(x.item));
        return res;
    }

    function anunciarEstadoTablero() {
        if (!estado.partida || estado.partida.estado !== 'jugando') {
            anunciarPolite("No hay partida en curso.");
            return;
        }
        const izq = estado.partida.extremo_izq !== null ? nombrePalo(estado.partida.extremo_izq) : 'vacío';
        const der = estado.partida.extremo_der !== null ? nombrePalo(estado.partida.extremo_der) : 'vacío';
        const turno = estado.miJugador && estado.miJugador.es_mi_turno ? 'tu turno' : `turno de ${estado.partida.nombre_turno}`;
        const pozo = estado.partida.pozo_count;

        anunciarPolite(`Estado del tablero: Extremo izquierdo es ${izq}, extremo derecho es ${der}. Es ${turno}. Quedan ${pozo} fichas en el pozo.`);
    }

    function anunciarResumenMano() {
        if (!estado.miMano || estado.miMano.length === 0) {
            anunciarPolite("No tienes fichas en tu mano.");
            return;
        }
        const extIzq = estado.partida && estado.partida.extremo_izq !== null ? Number(estado.partida.extremo_izq) : null;
        const extDer = estado.partida && estado.partida.extremo_der !== null ? Number(estado.partida.extremo_der) : null;
        const esMiTurno = Boolean(estado.miJugador && estado.miJugador.es_mi_turno);

        const descripciones = estado.miMano.map(item => {
            const f = item.ficha;
            const desc = describirFicha(f);
            if (!esMiTurno) return desc;
            if (extIzq === null || extDer === null) return `${desc}, jugable`;
            const encIzq = (f[0] === extIzq || f[1] === extIzq);
            const encDer = (f[0] === extDer || f[1] === extDer);
            if (encIzq && encDer && extIzq !== extDer) return `${desc}, jugable por ambos extremos`;
            if (encIzq) return `${desc}, jugable por la izquierda`;
            if (encDer) return `${desc}, jugable por la derecha`;
            return `${desc}, no encaja`;
        }).join('; ');

        anunciarPolite(`Tienes ${estado.miMano.length} fichas en tu mano: ${descripciones}`);
    }

    function anunciarResumenJugadores() {
        if (!estado.jugadores || estado.jugadores.length === 0) {
            anunciarPolite("No hay información de jugadores disponible.");
            return;
        }
        const resumen = estado.jugadores.map(j => {
            const pareja = (estado.partida && estado.partida.modo === 'parejas' && j.pareja) ? `, Pareja ${j.pareja}` : '';
            const enTurno = j.es_su_turno ? ', en turno' : '';
            return `${j.nombre}${pareja}: ${j.num_fichas} fichas${enTurno}`;
        }).join('; ');

        anunciarPolite(`Jugadores en la mesa: ${resumen}`);
    }

    // Efectos de sonido mediante Web Audio API (suaves y respetuosos)
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtxInstance = null;

    function reproducirTono(frecuencia, duracion, tipo = 'sine') {
        if (!estado.audioActivo) return;
        try {
            if (!audioCtxInstance) audioCtxInstance = new AudioCtx();
            if (audioCtxInstance.state === 'suspended') audioCtxInstance.resume();
            const osc = audioCtxInstance.createOscillator();
            const gain = audioCtxInstance.createGain();
            osc.type = tipo;
            osc.frequency.value = frecuencia;
            gain.gain.setValueAtTime(0.08, audioCtxInstance.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtxInstance.currentTime + duracion);
            osc.connect(gain);
            gain.connect(audioCtxInstance.destination);
            osc.start();
            osc.stop(audioCtxInstance.currentTime + duracion);
        } catch (e) {
            // Audio no disponible o bloqueado por navegador
        }
    }

    function sonidoTurnoPropio() {
        reproducirTono(587.33, 0.15); // Re5
        setTimeout(() => reproducirTono(880, 0.25), 150); // La5
    }

    function sonidoFichaJugada() {
        reproducirTono(330, 0.08, 'triangle');
    }

    // =========================================================================
    // PERSISTENCIA DE NOMBRE DEL JUGADOR (COOKIE + LOCALSTORAGE)
    // =========================================================================

    function guardarNombreJugador(nombre) {
        if (!nombre) return;
        try {
            localStorage.setItem('domino_nombre_jugador', nombre);
        } catch (e) {}
        document.cookie = "domino_nombre_jugador=" + encodeURIComponent(nombre) + "; path=/; max-age=31536000; SameSite=Lax";
    }

    function recuperarNombreJugador() {
        let nombre = '';
        try {
            nombre = localStorage.getItem('domino_nombre_jugador') || '';
        } catch (e) {}
        if (!nombre) {
            const match = document.cookie.match(/(?:^|;\s*)domino_nombre_jugador=([^;]*)/);
            if (match) nombre = decodeURIComponent(match[1]);
        }
        return nombre;
    }

    // =========================================================================
    // INICIALIZACIÓN Y GESTIÓN DE SALAS
    // =========================================================================

    function inicializar() {
        // Pre-rellenar nombre guardado en los formularios para no tener que escribirlo cada vez
        const nombreGuardado = recuperarNombreJugador();
        if (nombreGuardado) {
            const inputCrear = document.getElementById('crear-nombre');
            const inputUnirse = document.getElementById('unirse-nombre');
            if (inputCrear) inputCrear.value = nombreGuardado;
            if (inputUnirse) inputUnirse.value = nombreGuardado;
        }

        // Comprobar parámetros en la URL (ej: ?sala=DOM924)
        const params = new URLSearchParams(window.location.search);
        const salaUrl = params.get('sala');
        let tokenGuardado = sessionStorage.getItem('domino_token');
        let codigoGuardado = sessionStorage.getItem('domino_codigo');

        if (salaUrl) {
            const salaLimpia = salaUrl.trim().toUpperCase();
            DOM.unirseCodigo.value = salaLimpia;

            // Si la sala en la URL es diferente a la sesión guardada previamente, invalidar la sesión antigua
            if (codigoGuardado && codigoGuardado.toUpperCase() !== salaLimpia) {
                sessionStorage.removeItem('domino_token');
                sessionStorage.removeItem('domino_codigo');
                tokenGuardado = null;
                codigoGuardado = null;
            }

            // Consultar si la partida indicada en el enlace ya finalizó para abrirla directamente en modo estudio
            fetch(`estado.php?codigo=${encodeURIComponent(salaLimpia)}`)
                .then(r => r.json())
                .then(datos => {
                    if (datos.success && datos.partida && datos.partida.estado === 'terminada') {
                        estado.codigo = salaLimpia;
                        estado.token = '';
                        sessionStorage.removeItem('domino_token');
                        sessionStorage.removeItem('domino_codigo');
                        procesarDatosEstado(datos);
                        anunciarAssertive(`Partida finalizada ${salaLimpia} cargada para su estudio.`);
                    }
                })
                .catch(() => {});

            anunciarAssertive(`Invitación recibida para la sala ${salaLimpia}. Introduce tu nombre y pulsa Unirse a la Sala.`);

            // Situar el foco amigablemente en el formulario de unión
            setTimeout(() => {
                const inputNombre = document.getElementById('unirse-nombre');
                if (inputNombre && !inputNombre.value) {
                    inputNombre.focus();
                } else if (DOM.formUnirse) {
                    const btnUnirse = DOM.formUnirse.querySelector('button[type="submit"]');
                    if (btnUnirse) btnUnirse.focus();
                }
            }, 300);
        }

        // Recuperar sesión previa si existe
        if (tokenGuardado && codigoGuardado) {
            estado.token = tokenGuardado;
            estado.codigo = codigoGuardado;
            iniciarPolling();
        }

        configurarEventosUI();
        configurarAtajosTeclado();
    }

    function configurarEventosUI() {
        // Toggle de Alto Contraste
        DOM.btnAltoContraste.addEventListener('click', () => {
            const activo = document.body.classList.toggle('alto-contraste');
            DOM.btnAltoContraste.setAttribute('aria-pressed', activo ? 'true' : 'false');
            anunciarPolite(activo ? 'Modo de alto contraste activado.' : 'Modo de alto contraste desactivado.');
        });

        // Diálogo de Atajos
        DOM.btnAyudaAtajos.addEventListener('click', () => {
            DOM.modalAtajos.classList.remove('oculto');
            DOM.btnCerrarAtajos.focus();
            anunciarPolite('Ventana de ayuda de atajos de teclado abierta.');
        });

        DOM.btnCerrarAtajos.addEventListener('click', () => {
            DOM.modalAtajos.classList.add('oculto');
            DOM.btnAyudaAtajos.focus();
            anunciarPolite('Ventana de ayuda cerrada.');
        });

        // Crear Partida
        DOM.crearMaxJugadores.addEventListener('change', (e) => {
            const max = parseInt(e.target.value, 10);
            if (max !== 4) {
                DOM.crearModo.value = 'individual';
                DOM.crearModo.disabled = true;
            } else {
                DOM.crearModo.disabled = false;
            }
        });

        DOM.formCrear.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nombre = document.getElementById('crear-nombre').value.trim();
            const maxJugadores = parseInt(DOM.crearMaxJugadores.value, 10);
            const modo = DOM.crearModo.value;

            if (!nombre) return;
            guardarNombreJugador(nombre);

            try {
                const resp = await fetch('crear.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nombre, max_jugadores: maxJugadores, modo })
                });
                const datos = await resp.json();

                if (datos.success) {
                    estado.codigo = datos.codigo;
                    estado.token = datos.token;
                    estado.esAnfitrion = true;
                    sessionStorage.setItem('domino_token', estado.token);
                    sessionStorage.setItem('domino_codigo', estado.codigo);

                    mostrarPantalla('espera');
                    actualizarEnlaceInvitacion();
                    anunciarAssertive(`Partida creada con éxito. Código de sala: ${estado.codigo}. Esperando jugadores.`);
                    iniciarPolling();
                    setTimeout(() => {
                        if (DOM.btnAgregarBot && !DOM.btnAgregarBot.disabled && DOM.btnAgregarBot.style.display !== 'none') {
                            DOM.btnAgregarBot.focus();
                        }
                    }, 120);
                } else {
                    alert(datos.error || 'Error al crear la sala.');
                }
            } catch (err) {
                alert('Error de conexión con el servidor.');
            }
        });

        // Unirse a Partida
        DOM.formUnirse.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nombre = document.getElementById('unirse-nombre').value.trim();
            const codigo = DOM.unirseCodigo.value.trim().toUpperCase();

            if (!nombre || !codigo) return;
            guardarNombreJugador(nombre);

            try {
                const resp = await fetch('unirse.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nombre, codigo })
                });
                const datos = await resp.json();

                if (datos.success) {
                    if (datos.partida_terminada) {
                        estado.codigo = datos.codigo;
                        estado.token = '';
                        sessionStorage.removeItem('domino_token');
                        sessionStorage.removeItem('domino_codigo');
                        anunciarAssertive("Partida finalizada. Cargando el visor de estudio con el tablero y todas las jugadas.");
                        await consultarEstado();
                        return;
                    }

                    estado.codigo = datos.codigo;
                    estado.token = datos.token;
                    estado.esAnfitrion = false;
                    sessionStorage.setItem('domino_token', estado.token);
                    sessionStorage.setItem('domino_codigo', estado.codigo);

                    mostrarPantalla('espera');
                    actualizarEnlaceInvitacion();
                    anunciarAssertive(`Te has unido a la sala ${estado.codigo}. Esperando al anfitrión.`);
                    iniciarPolling();
                } else {
                    alert(datos.error || 'Error al unirse a la sala.');
                }
            } catch (err) {
                alert('Error de conexión con el servidor.');
            }
        });

        // Copiar Enlace
        DOM.btnCopiarEnlace.addEventListener('click', () => {
            DOM.inputInvitacion.select();
            navigator.clipboard.writeText(DOM.inputInvitacion.value).then(() => {
                anunciarPolite('Enlace de invitación copiado al portapapeles.');
                DOM.btnCopiarEnlace.textContent = '✅ ¡Copiado!';
                setTimeout(() => {
                    DOM.btnCopiarEnlace.textContent = '📋 Copiar Enlace';
                }, 2500);
            }).catch(() => {
                anunciarPolite('Por favor selecciona y copia el enlace manualmente.');
            });
        });

        // Añadir Máquina a la partida
        DOM.btnAgregarBot.addEventListener('click', async () => {
            DOM.btnAgregarBot.disabled = true;
            try {
                const resp = await fetch('agregar_gemini.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        codigo: estado.codigo,
                        token: estado.token
                    })
                });
                const datos = await resp.json();

                if (datos.success) {
                    const maxPlazas = (estado.partida && estado.partida.max_jugadores) ? estado.partida.max_jugadores : 4;
                    const plazasLlenas = (datos.total_jugadores >= maxPlazas);
                    const plazasTexto = plazasLlenas ? 'Sala completa.' : `${datos.total_jugadores} de ${maxPlazas} plazas.`;
                    anunciarAssertive(`Añadida ${datos.bot_nombre}. ${plazasTexto}`);
                    estado.ultimoTotalJugadoresEspera = datos.total_jugadores;
                    await consultarEstado();

                    if (plazasLlenas) {
                        if (DOM.btnIniciarPartida && !DOM.btnIniciarPartida.disabled) {
                            setTimeout(() => {
                                DOM.btnIniciarPartida.focus();
                            }, 60);
                        }
                    } else {
                        // Mientras tanto, se focaliza para añadir más participantes
                        if (DOM.btnAgregarBot && !DOM.btnAgregarBot.disabled && DOM.btnAgregarBot.style.display !== 'none') {
                            setTimeout(() => {
                                DOM.btnAgregarBot.focus();
                            }, 60);
                        }
                    }
                } else {
                    alert(datos.error || 'No se pudo añadir la máquina.');
                }
            } catch (err) {
                alert('Error al comunicar con el servidor.');
            } finally {
                if (DOM.btnAgregarBot && DOM.btnAgregarBot.style.display !== 'none') {
                    DOM.btnAgregarBot.disabled = false;
                }
            }
        });

        // Iniciar Partida
        DOM.btnIniciarPartida.addEventListener('click', async () => {
            DOM.btnIniciarPartida.disabled = true;
            try {
                const resp = await fetch('iniciar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codigo: estado.codigo, token: estado.token })
                });
                const datos = await resp.json();

                if (datos.success) {
                    // No anunciamos la salida aquí para evitar duplicarla con el anuncio inicial de la mesa
                    await consultarEstado();
                } else {
                    alert(datos.error || 'No se pudo iniciar la partida.');
                    DOM.btnIniciarPartida.disabled = false;
                }
            } catch (err) {
                alert('Error al iniciar la partida.');
                DOM.btnIniciarPartida.disabled = false;
            }
        });

        // Botón Robar
        DOM.btnRobar.addEventListener('click', ejecutarRobo);

        // Botón Pasar
        DOM.btnPasar.addEventListener('click', ejecutarPase);

        // Botones de consulta rápida para accesibilidad móvil (VoiceOver / TalkBack)
        if (DOM.btnEstadoTablero) {
            DOM.btnEstadoTablero.addEventListener('click', anunciarEstadoTablero);
        }
        if (DOM.btnResumenJugadores) {
            DOM.btnResumenJugadores.addEventListener('click', anunciarResumenJugadores);
        }

        // Delegación de clics en la mano de fichas (evita cierres desfasados / stale closures)
        DOM.contenedorMano.addEventListener('click', (e) => {
            const btn = e.target.closest('.ficha-btn');
            if (!btn) return;
            const fichaStr = btn.getAttribute('data-ficha');
            if (!fichaStr) return;
            const partes = fichaStr.split('-').map(Number);
            if (partes.length === 2) {
                gestionarClickFicha(partes);
            }
        });

        // Modal de Elección de Extremo
        DOM.btnExtremoIzq.addEventListener('click', () => {
            if (estado.fichaSeleccionadaParaExtremo) {
                enviarJugada(estado.fichaSeleccionadaParaExtremo, 'izquierda');
                cerrarModalExtremo();
            }
        });

        DOM.btnExtremoDer.addEventListener('click', () => {
            if (estado.fichaSeleccionadaParaExtremo) {
                enviarJugada(estado.fichaSeleccionadaParaExtremo, 'derecha');
                cerrarModalExtremo();
            }
        });

        DOM.btnCancelarExtremo.addEventListener('click', cerrarModalExtremo);

        // Botón Abandonar Partida
        if (DOM.btnAbandonarPartida) {
            DOM.btnAbandonarPartida.addEventListener('click', () => {
                if (!confirm("¿Deseas abandonar la partida actual y volver al menú principal?")) {
                    return;
                }
                if (estado.pollingTimer) {
                    clearInterval(estado.pollingTimer);
                    estado.pollingTimer = null;
                }
                sessionStorage.removeItem('domino_token');
                sessionStorage.removeItem('domino_codigo');
                estado.codigo = null;
                estado.token = null;
                estado.partida = null;
                estado.miJugador = null;
                estado.miMano = [];
                estado.anuncioInicialRealizado = false;

                mostrarPantalla('inicio');
                anunciarAssertive("Has abandonado la partida.");
            });
        }

        // Botón Nueva Partida
        DOM.btnNuevaPartida.addEventListener('click', () => {
            sessionStorage.removeItem('domino_token');
            sessionStorage.removeItem('domino_codigo');
            window.location.href = window.location.pathname;
        });
    }

    function actualizarEnlaceInvitacion() {
        const urlBase = window.location.origin + window.location.pathname;
        const enlace = `${urlBase}?sala=${estado.codigo}`;
        DOM.inputInvitacion.value = enlace;
        DOM.esperaCodigo.textContent = estado.codigo;
    }

    function mostrarPantalla(nombre) {
        if (estado.pantallaActual === nombre) return;
        estado.pantallaActual = nombre;

        DOM.pantallaInicio.classList.toggle('oculto', nombre !== 'inicio');
        DOM.pantallaEspera.classList.toggle('oculto', nombre !== 'espera');
        DOM.pantallaJuego.classList.toggle('oculto', nombre !== 'juego');
        DOM.pantallaFin.classList.toggle('oculto', nombre !== 'fin');

        if (DOM.btnManual) {
            DOM.btnManual.classList.toggle('oculto', nombre === 'juego');
        }
        if (DOM.btnAltoContraste) {
            DOM.btnAltoContraste.classList.toggle('oculto', nombre === 'juego');
            if (nombre === 'juego') {
                DOM.btnAltoContraste.style.display = 'none';
                DOM.btnAltoContraste.setAttribute('hidden', '');
            } else {
                DOM.btnAltoContraste.style.display = '';
                DOM.btnAltoContraste.removeAttribute('hidden');
            }
        }
    }

    // =========================================================================
    // POLLING AJAX CADA 2 SEGUNDOS Y ESTABILIDAD DEL FOCO
    // =========================================================================

    function iniciarPolling() {
        if (estado.pollingTimer) clearInterval(estado.pollingTimer);
        consultarEstado();
        estado.pollingTimer = setInterval(consultarEstado, 2000);
    }

    let consultandoEstado = false;

    async function consultarEstado() {
        if (!estado.codigo || consultandoEstado) return;
        consultandoEstado = true;

        try {
            const desdeH = estado.ultimoHistorialId || 0;
            const url = `estado.php?codigo=${encodeURIComponent(estado.codigo)}&token=${encodeURIComponent(estado.token)}&desde_historial_id=${desdeH}`;
            const resp = await fetch(url);
            if (!resp.ok) return;

            const datos = await resp.json();
            if (!datos.success) return;

            procesarDatosEstado(datos);

        } catch (err) {
            console.warn('Error en sondeo de estado:', err);
        } finally {
            consultandoEstado = false;
        }
    }

    function procesarDatosEstado(datos) {
        if (estado.ultimoHistorialId === undefined) {
            estado.ultimoHistorialId = 0;
        }
        if (estado.ultimoMovimientoIdProcesado === undefined) {
            estado.ultimoMovimientoIdProcesado = 0;
            estado.ultimoMovimientoRegistrado = datos.partida ? datos.partida.ultimo_movimiento : '';
        }
        estado.partida = datos.partida;
        estado.jugadores = datos.jugadores || [];
        estado.miJugador = datos.mi_jugador || null;
        estado.miMano = ordenarManoEstrategica(datos.mi_mano || []);

        if (estado.miJugador) {
            estado.esAnfitrion = estado.miJugador.es_anfitrion;
        }

        if (estado.partida.estado === 'esperando') {
            mostrarPantalla('espera');
            renderizarSalaEspera();
        } else if (estado.partida.estado === 'jugando') {
            mostrarPantalla('juego');
            renderizarMesaJuego(datos);
        } else if (estado.partida.estado === 'terminada') {
            mostrarPantalla('fin');
            renderizarFinPartida(datos);
        }

        if (datos.ultimo_historial_id) {
            estado.ultimoHistorialId = Math.max(estado.ultimoHistorialId || 0, Number(datos.ultimo_historial_id));
        }
    }

    // =========================================================================
    // RENDERIZADO: SALA DE ESPERA
    // =========================================================================

    function renderizarSalaEspera() {
        const modo = estado.partida.modo === 'parejas' ? 'Por Parejas (2 vs 2)' : 'Individual';
        if (DOM.esperaModoTexto.textContent !== modo) DOM.esperaModoTexto.textContent = modo;
        const plazasTexto = `${estado.jugadores.length} / ${estado.partida.max_jugadores}`;
        if (DOM.esperaPlazasTexto.textContent !== plazasTexto) DOM.esperaPlazasTexto.textContent = plazasTexto;
        if (DOM.esperaCuenta.textContent !== String(estado.jugadores.length)) DOM.esperaCuenta.textContent = estado.jugadores.length;

        // Renderizar lista de jugadores SOLO si cambió para no provocar relecturas innecesarias
        const listaJugadoresClave = estado.jugadores.map(j => `${j.id}:${j.nombre}:${j.es_bot}:${j.pareja || ''}`).join('|');
        if (listaJugadoresClave !== estado.ultimaListaEsperaClave) {
            // Anuncio discreto solo si se añade un nuevo jugador a la sala
            if (estado.ultimaListaEsperaClave && estado.jugadores.length > (estado.ultimoTotalJugadoresEspera || 0)) {
                const nuevo = estado.jugadores[estado.jugadores.length - 1];
                if (nuevo) {
                    anunciarPolite(`Se unió ${nuevo.nombre}. Hay ${estado.jugadores.length} jugadores.`);
                }
            }
            estado.ultimaListaEsperaClave = listaJugadoresClave;
            estado.ultimoTotalJugadoresEspera = estado.jugadores.length;

            DOM.listaJugadoresEspera.innerHTML = '';
            estado.jugadores.forEach(j => {
                const li = document.createElement('li');
                if (j.es_bot) li.classList.add('es-bot');

                const icono = j.es_bot ? '🤖' : '👤';
                let etiquetaPareja = '';
                if (estado.partida.modo === 'parejas' && j.pareja) {
                    etiquetaPareja = ` - Pareja ${j.pareja}`;
                }

                li.innerHTML = `
                    <span aria-hidden="true">${icono}</span>
                    <span>${j.nombre}${etiquetaPareja}</span>
                `;
                DOM.listaJugadoresEspera.appendChild(li);
            });
        }

        // Controles de Anfitrión
        const maxRequerido = (estado.partida && estado.partida.max_jugadores) ? Number(estado.partida.max_jugadores) : 4;
        const plazasLlenas = (estado.jugadores.length >= maxRequerido);
        if (DOM.btnAgregarBot) DOM.btnAgregarBot.disabled = plazasLlenas || !estado.esAnfitrion;

        const puedeIniciar = Boolean(estado.esAnfitrion && (estado.jugadores.length === maxRequerido));

        DOM.btnIniciarPartida.disabled = !puedeIniciar;

        if (!estado.esAnfitrion) {
            if (DOM.esperaMensajeEstado.textContent !== 'Esperando a que el anfitrión inicie la partida.') {
                DOM.esperaMensajeEstado.textContent = 'Esperando a que el anfitrión inicie la partida.';
            }
            if (DOM.btnAgregarBot) {
                DOM.btnAgregarBot.style.display = 'none';
                DOM.btnAgregarBot.setAttribute('hidden', '');
            }
            DOM.btnIniciarPartida.style.display = 'none';
            DOM.btnIniciarPartida.setAttribute('hidden', '');
        } else {
            DOM.btnIniciarPartida.style.display = 'inline-flex';
            DOM.btnIniciarPartida.removeAttribute('hidden');

            if (DOM.btnAgregarBot) {
                if (plazasLlenas) {
                    // Cuando no se pueden añadir más participantes, este botón desaparece
                    const estabaEnfocadoElBotonBot = (document.activeElement === DOM.btnAgregarBot);
                    DOM.btnAgregarBot.style.display = 'none';
                    DOM.btnAgregarBot.setAttribute('hidden', '');
                    DOM.btnAgregarBot.disabled = true;

                    // Y se focaliza Comenzar
                    if (estabaEnfocadoElBotonBot && !DOM.btnIniciarPartida.disabled) {
                        DOM.btnIniciarPartida.focus();
                    }
                } else {
                    DOM.btnAgregarBot.style.display = 'inline-flex';
                    DOM.btnAgregarBot.removeAttribute('hidden');
                    DOM.btnAgregarBot.disabled = false;
                }
            }

            const textoNuevo = puedeIniciar
                ? '¡Todo listo! Pulsa "Comenzar Partida" para iniciar el juego.'
                : 'Añade más jugadores con el enlace o pulsa "Añadir Máquina".';
            if (DOM.esperaMensajeEstado.textContent !== textoNuevo) {
                DOM.esperaMensajeEstado.textContent = textoNuevo;
            }
        }
    }

    // =========================================================================
    // RENDERIZADO: MESA DE JUEGO (CON ESTABILIDAD DE FOCO Y WCAG 2.1 AA)
    // =========================================================================

    function renderizarMesaJuego(datos) {
        // 1. ANUNCIO INICIAL DE LA PARTIDA (Solo una vez al comenzar la ronda)
        if (!estado.anuncioInicialRealizado && estado.miMano.length > 0) {
            estado.anuncioInicialRealizado = true;
            estado.ultimoMovimientoRegistrado = estado.partida.ultimo_movimiento || '';
        }

        // 2. DETECCIÓN DE NUEVOS MOVIMIENTOS CONTINUOS (1, 2 o 3 rivales)
        // Basado estrictamente en el ID incremental de historial para evitar repeticiones
        if (Array.isArray(datos.nuevos_movimientos) && datos.nuevos_movimientos.length > 0) {
            datos.nuevos_movimientos.forEach(mov => {
                const movId = Number(mov.id);
                if (movId > (estado.ultimoMovimientoIdProcesado || 0)) {
                    estado.ultimoMovimientoIdProcesado = movId;
                    estado.ultimoMovimientoRegistrado = mov.descripcion;
                    agregarLineaHistorial(mov.descripcion);
                    sonidoFichaJugada();

                    const esMio = estado.miJugador && Number(mov.jugador_id) === Number(estado.miJugador.id);
                    const esAvisoGeneral = mov.descripcion && (mov.descripcion.includes('5 dobles') || mov.descripcion.includes('barajar'));
                    if (!esMio || esAvisoGeneral) {
                        if (datos.nuevos_movimientos.length <= 2 || mov === datos.nuevos_movimientos[datos.nuevos_movimientos.length - 1] || esAvisoGeneral) {
                            anunciarPolite(mov.descripcion);
                        }
                    }
                }
            });
            if (estado.partida && estado.partida.ultimo_movimiento) {
                estado.ultimoMovimientoRegistrado = estado.partida.ultimo_movimiento;
            }
        } else if (estado.partida.ultimo_movimiento && DOM.registroMovimientos.children.length === 0) {
            agregarLineaHistorial(estado.partida.ultimo_movimiento);
        }

        // 3. (Comentarios de Máquina omitidos a petición del usuario)

        // 4. CAMBIO DE TURNO Y ALERTA SONORA (Solo una vez por cambio de turno)
        const turnoActualId = estado.partida && estado.partida.turno_jugador_id !== null ? String(estado.partida.turno_jugador_id) : '';
        const esMiTurno = Boolean(estado.miJugador && estado.miJugador.es_mi_turno);
        const acabaDeRecibirTurno = esMiTurno && !estado.eraMiTurnoPreviamente;
        estado.eraMiTurnoPreviamente = esMiTurno;

        if (esMiTurno) {
            if (estado.ultimoTurnoAnunciadoId !== turnoActualId) {
                estado.ultimoTurnoAnunciadoId = turnoActualId;
                sonidoTurnoPropio();
            }
        } else {
            estado.ultimoTurnoAnunciadoId = null;
        }

        // Actualizar barra de estado superior solo si cambió el texto
        const textoEsperado = esMiTurno ? "¡TU TURNO!" : (estado.partida.nombre_turno || 'Esperando...');
        if (DOM.textoTurno.textContent !== textoEsperado) {
            DOM.textoTurno.textContent = textoEsperado;
        }
        DOM.cajaTurno.style.backgroundColor = esMiTurno ? "rgba(34, 197, 94, 0.2)" : "transparent";

        DOM.textoExtremoIzq.textContent = estado.partida.extremo_izq !== null ? estado.partida.extremo_izq : '-';
        DOM.textoExtremoDer.textContent = estado.partida.extremo_der !== null ? estado.partida.extremo_der : '-';
        DOM.textoPozoCuenta.textContent = estado.partida.pozo_count;

        // 5. RESUMEN DE RIVALES (Sin revelar sus fichas, solo cantidad numérica)
        renderizarRivales();

        // 6. RENDERIZADO DEL TABLERO
        renderizarTablero();

        // 7. RENDERIZADO DE LA MANO CON ESTABILIDAD DE FOCO ABSOLUTA
        // Comparamos el JSON para evitar destruir botones si las fichas no cambiaron
        const nuevaManoJSON = JSON.stringify(estado.miMano.map(f => f.ficha));
        if (nuevaManoJSON !== estado.ultimaManoJSON) {
            estado.ultimaManoJSON = nuevaManoJSON;
            construirBotonesMano();
        } else {
            actualizarAtributosBotonesMano();
        }

        const numFichas = estado.miMano.length;
        if (DOM.tituloMano) {
            DOM.tituloMano.textContent = `Tienes ${numFichas} ${numFichas === 1 ? 'ficha' : 'fichas'}`;
        }
        if (DOM.cuentaMano) {
            DOM.cuentaMano.textContent = numFichas;
        }

        // Auto-enfoque en el encabezado de la mano al recibir el turno:
        // Se ejecuta justo tras terminar de anunciar las jugadas de los rivales previos (1, 2 o 3 jugadores)
        if (acabaDeRecibirTurno) {
            ejecutarAlVaciarCola(() => {
                if (DOM.tituloMano && Boolean(estado.miJugador && estado.miJugador.es_mi_turno)) {
                    DOM.tituloMano.focus();
                }
            });
        }

        // 8. BOTONES DE ACCIÓN (ROBAR / PASAR)
        // Regla estricta de juego accesible:
        // - Si el jugador puede robar (su turno, sin ficha jugable y pozo > 0): mostrar Robar, ocultar Pasar.
        // - Si el jugador puede pasar (su turno, sin ficha jugable y pozo == 0): mostrar Pasar, ocultar Robar.
        // - Si tiene fichas jugables o no es su turno: ocultar ambos para no estorbar en pantalla ni al lector.
        if (datos.puede_robar) {
            DOM.btnRobar.style.display = '';
            DOM.btnRobar.removeAttribute('hidden');
            DOM.btnRobar.disabled = false;
        } else {
            DOM.btnRobar.style.display = 'none';
            DOM.btnRobar.setAttribute('hidden', '');
            DOM.btnRobar.disabled = true;
        }

        if (datos.puede_pasar) {
            DOM.btnPasar.style.display = '';
            DOM.btnPasar.removeAttribute('hidden');
            DOM.btnPasar.disabled = false;
        } else {
            DOM.btnPasar.style.display = 'none';
            DOM.btnPasar.setAttribute('hidden', '');
            DOM.btnPasar.disabled = true;
        }
    }

    function enfocarPrimeraFicha() {
        if (!DOM.contenedorMano) return;
        const primeraJugable = DOM.contenedorMano.querySelector('.ficha-btn.jugable');
        if (primeraJugable) {
            primeraJugable.focus();
            return;
        }
        const primeraFicha = DOM.contenedorMano.querySelector('.ficha-btn');
        if (primeraFicha) {
            primeraFicha.focus();
            return;
        }
        if (DOM.btnRobar && !DOM.btnRobar.disabled && DOM.btnRobar.style.display !== 'none') {
            DOM.btnRobar.focus();
        } else if (DOM.btnPasar && !DOM.btnPasar.disabled && DOM.btnPasar.style.display !== 'none') {
            DOM.btnPasar.focus();
        }
    }

    function renderizarRivales() {
        DOM.contenedorRivales.innerHTML = '';
        estado.jugadores.forEach(j => {
            const esYo = estado.miJugador && (j.id === estado.miJugador.id);
            const tarjeta = document.createElement('div');
            tarjeta.className = 'tarjeta-rival';
            if (j.es_su_turno) tarjeta.classList.add('turno-activo');

            let subtexto = `${j.num_fichas} fichas`;
            if (estado.partida.modo === 'parejas' && j.pareja) {
                subtexto += ` | Pareja ${j.pareja}`;
            }

            const icono = j.es_bot ? '🤖' : '👤';
            tarjeta.innerHTML = `
                <div class="nombre-rival">
                    ${icono} ${j.nombre} ${esYo ? '(Tú)' : ''}
                </div>
                <div class="info-fichas">${subtexto}</div>
                ${j.es_su_turno ? '<strong style="color:var(--foco-color); font-size:0.85rem;">En turno</strong>' : ''}
            `;
            DOM.contenedorRivales.appendChild(tarjeta);
        });
    }

    function renderizarTablero() {
        const fichasTab = estado.partida.tablero_fichas || [];
        DOM.cuentaTablero.textContent = fichasTab.length;
        DOM.fichasTablero.innerHTML = '';

        const extIzq = estado.partida.extremo_izq !== null ? nombrePalo(estado.partida.extremo_izq) : '-';
        const extDer = estado.partida.extremo_der !== null ? nombrePalo(estado.partida.extremo_der) : '-';
        DOM.tableroScroll.setAttribute('aria-label', `Tablero con ${fichasTab.length} fichas. Extremo izquierdo ${extIzq}, extremo derecho ${extDer}.`);

        fichasTab.forEach((f) => {
            const div = document.createElement('div');
            const esDoble = (f[0] === f[1]);
            div.className = `ficha-tablero ${esDoble ? 'doble' : ''}`;
            div.setAttribute('role', 'img');
            div.setAttribute('aria-label', describirFicha(f));

            div.innerHTML = `
                <div class="mitad" aria-hidden="true">${f[0]}</div>
                <div class="divisor" aria-hidden="true"></div>
                <div class="mitad" aria-hidden="true">${f[1]}</div>
            `;
            DOM.fichasTablero.appendChild(div);
        });

        // Auto-scroll horizontal suave para ver la última ficha jugada
        DOM.tableroScroll.scrollLeft = DOM.tableroScroll.scrollWidth;
    }

    // Reconstruye el DOM de la mano únicamente cuando el jugador juega o roba una ficha
    function construirBotonesMano() {
        // Guardar identificador del foco actual si estaba en la mano
        const elementoActivo = document.activeElement;
        const fichaFocoPrevio = elementoActivo ? elementoActivo.getAttribute('data-ficha') : null;

        DOM.contenedorMano.innerHTML = '';

        if (estado.miMano.length === 0) {
            DOM.contenedorMano.innerHTML = '<p class="mano-vacia">No tienes fichas en tu mano.</p>';
            return;
        }

        const extIzq = estado.partida && estado.partida.extremo_izq !== null ? Number(estado.partida.extremo_izq) : null;
        const extDer = estado.partida && estado.partida.extremo_der !== null ? Number(estado.partida.extremo_der) : null;
        const esMiTurno = Boolean(estado.miJugador && estado.miJugador.es_mi_turno);

        estado.miMano.forEach((item, index) => {
            const f = item.ficha;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ficha-btn';
            btn.setAttribute('data-ficha', `${f[0]}-${f[1]}`);
            btn.setAttribute('data-index', String(index));

            actualizarBotonFichaIndividual(btn, f, extIzq, extDer, esMiTurno);

            btn.innerHTML = `
                <div class="mitad-btn" aria-hidden="true">${f[0]}</div>
                <div class="divisor-btn" aria-hidden="true"></div>
                <div class="mitad-btn" aria-hidden="true">${f[1]}</div>
            `;

            // Soporte de navegación por flechas de teclado entre fichas
            btn.addEventListener('keydown', (e) => {
                gestionarFlechasTecladoMano(e, index);
            });

            DOM.contenedorMano.appendChild(btn);
        });

        // Restaurar foco si el elemento anterior aún existe
        if (fichaFocoPrevio) {
            const nuevoElementoFoco = DOM.contenedorMano.querySelector(`[data-ficha="${fichaFocoPrevio}"]`);
            if (nuevoElementoFoco) {
                nuevoElementoFoco.focus();
            }
        }
    }

    function actualizarBotonFichaIndividual(btn, f, extIzq, extDer, esMiTurno) {
        const desc = describirFicha(f);
        let encajaIzq = false;
        let encajaDer = false;
        let jugable = false;

        if (extIzq === null || extDer === null) {
            encajaIzq = true;
            encajaDer = true;
            jugable = esMiTurno;
        } else {
            encajaIzq = (f[0] === extIzq || f[1] === extIzq);
            encajaDer = (f[0] === extDer || f[1] === extDer);
            jugable = esMiTurno && (encajaIzq || encajaDer);
        }

        let ariaLabel = desc;
        if (esMiTurno) {
            if (encajaIzq && encajaDer && extIzq !== extDer) {
                ariaLabel += `, jugable por ambos extremos (izquierda ${nombrePalo(extIzq)}, derecha ${nombrePalo(extDer)})`;
            } else if (encajaIzq && !encajaDer) {
                ariaLabel += `, jugable por la izquierda (${nombrePalo(extIzq)})`;
            } else if (encajaDer && !encajaIzq) {
                ariaLabel += `, jugable por la derecha (${nombrePalo(extDer)})`;
            } else if (encajaIzq && encajaDer && extIzq === extDer) {
                ariaLabel += `, jugable en el ${nombrePalo(extIzq)}`;
            } else {
                ariaLabel += `, no encaja actualmente`;
            }
        } else {
            ariaLabel += `, en espera de tu turno`;
        }

        btn.setAttribute('aria-label', ariaLabel);
        btn.setAttribute('data-encaja-izq', encajaIzq ? 'true' : 'false');
        btn.setAttribute('data-encaja-der', encajaDer ? 'true' : 'false');
        btn.setAttribute('data-jugable', jugable ? 'true' : 'false');

        if (jugable) {
            btn.classList.add('jugable');
            btn.disabled = false;
            btn.removeAttribute('aria-disabled');
        } else {
            btn.classList.remove('jugable');
            // Mantener enfocable para que lectores de pantalla puedan explorar toda la mano
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'true');
        }
    }

    // Actualiza atributos sin tocar la jerarquía DOM para que NVDA y VoiceOver no pierdan el foco
    function actualizarAtributosBotonesMano() {
        const extIzq = estado.partida && estado.partida.extremo_izq !== null ? Number(estado.partida.extremo_izq) : null;
        const extDer = estado.partida && estado.partida.extremo_der !== null ? Number(estado.partida.extremo_der) : null;
        const esMiTurno = Boolean(estado.miJugador && estado.miJugador.es_mi_turno);

        const botones = DOM.contenedorMano.querySelectorAll('.ficha-btn');
        botones.forEach((btn, index) => {
            const item = estado.miMano[index];
            if (!item) return;
            actualizarBotonFichaIndividual(btn, item.ficha, extIzq, extDer, esMiTurno);
        });
    }

    function gestionarFlechasTecladoMano(e, currentIndex) {
        const botones = DOM.contenedorMano.querySelectorAll('.ficha-btn');
        let nextIndex = currentIndex;

        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            nextIndex = (currentIndex + 1) % botones.length;
            botones[nextIndex].focus();
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            nextIndex = (currentIndex - 1 + botones.length) % botones.length;
            botones[nextIndex].focus();
        }
    }

    // =========================================================================
    // JUGADA DE FICHAS Y DIÁLOGO MODAL DUAL
    // =========================================================================

    function gestionarClickFicha(ficha) {
        if (!estado.miJugador || !estado.miJugador.es_mi_turno) {
            anunciarPolite('Aún no es tu turno de jugar.');
            return;
        }

        const extIzq = estado.partida && estado.partida.extremo_izq !== null ? Number(estado.partida.extremo_izq) : null;
        const extDer = estado.partida && estado.partida.extremo_der !== null ? Number(estado.partida.extremo_der) : null;
        const desc = describirFicha(ficha);

        // Caso 0: Tablero vacío (primera jugada de la ronda)
        if (extIzq === null || extDer === null) {
            enviarJugada(ficha, 'derecha');
            return;
        }

        const encajaIzq = (ficha[0] === extIzq || ficha[1] === extIzq);
        const encajaDer = (ficha[0] === extDer || ficha[1] === extDer);

        // Caso no encaja
        if (!encajaIzq && !encajaDer) {
            anunciarAssertive(`La ${desc} no encaja en los extremos actuales (${nombrePalo(extIzq)} o ${nombrePalo(extDer)}).`);
            return;
        }

        // Caso 1: Encaja en ambos extremos y son diferentes números -> Modal de elección
        if (encajaIzq && encajaDer && extIzq !== extDer) {
            abrirModalExtremo(ficha, extIzq, extDer);
            return;
        }

        // Caso 2: Solo encaja por la izquierda
        if (encajaIzq && !encajaDer) {
            enviarJugada(ficha, 'izquierda');
            return;
        }

        // Caso 3: Solo encaja por la derecha
        if (encajaDer && !encajaIzq) {
            enviarJugada(ficha, 'derecha');
            return;
        }

        // Caso 4: Encaja en ambos pero ambos extremos son iguales (ej: ambos son 6)
        if (encajaIzq && encajaDer && extIzq === extDer) {
            enviarJugada(ficha, 'derecha');
            return;
        }
    }

    function abrirModalExtremo(ficha, extIzq, extDer) {
        estado.fichaSeleccionadaParaExtremo = ficha;
        DOM.numExtremoIzq.textContent = (extIzq === 0) ? 'blanca' : extIzq;
        DOM.numExtremoDer.textContent = (extDer === 0) ? 'blanca' : extDer;
        DOM.modalExtremo.classList.remove('oculto');
        DOM.btnExtremoIzq.focus();

        const desc = describirFicha(ficha);
        const izqNombre = nombrePalo(extIzq);
        const derNombre = nombrePalo(extDer);
        anunciarAssertive(`La ${desc} puede jugarse por la izquierda en el ${izqNombre} o por la derecha en el ${derNombre}. Elige un botón.`);
    }

    function cerrarModalExtremo() {
        DOM.modalExtremo.classList.add('oculto');
        estado.fichaSeleccionadaParaExtremo = null;
        // Devolver foco a la mano
        const primerBoton = DOM.contenedorMano.querySelector('.ficha-btn');
        if (primerBoton) primerBoton.focus();
    }

    async function enviarJugada(ficha, extremo) {
        try {
            const resp = await fetch('jugar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    codigo: estado.codigo,
                    token: estado.token,
                    ficha: ficha,
                    extremo: extremo
                })
            });
            const datos = await resp.json();

            if (datos.success) {
                interrumpirYAnunciar("Ok");
                await consultarEstado();
                setTimeout(consultarEstado, 900);
            } else {
                anunciarAssertive(datos.error || 'Jugada inválida.');
            }
        } catch (err) {
            anunciarAssertive('Error al comunicar la jugada con el servidor.');
        }
    }

    // =========================================================================
    // ACCIONES: ROBAR Y PASAR
    // =========================================================================

    async function ejecutarRobo() {
        DOM.btnRobar.disabled = true;
        try {
            const resp = await fetch('robar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    codigo: estado.codigo,
                    token: estado.token
                })
            });
            const datos = await resp.json();

            if (datos.success) {
                if (datos.es_valida && datos.ficha) {
                    // Si la ficha robada encaja, ponerla y anunciarla automáticamente
                    let ext = 'derecha';
                    if (datos.encaja_izq && !datos.encaja_der) {
                        ext = 'izquierda';
                    } else if (datos.encaja_der && !datos.encaja_izq) {
                        ext = 'derecha';
                    } else {
                        ext = 'derecha';
                    }
                    await enviarJugada(datos.ficha, ext);
                } else {
                    anunciarPolite(datos.mensaje);
                    await consultarEstado();
                    setTimeout(() => {
                        if (DOM.btnRobar && !DOM.btnRobar.disabled && DOM.btnRobar.style.display !== 'none') {
                            DOM.btnRobar.focus();
                        } else if (DOM.btnPasar && !DOM.btnPasar.disabled && DOM.btnPasar.style.display !== 'none') {
                            DOM.btnPasar.focus();
                        }
                    }, 120);
                }
            } else {
                anunciarPolite(datos.error || 'No puedes robar en este momento.');
            }
        } catch (err) {
            anunciarPolite('Error de comunicación al robar ficha.');
        }
    }

    async function ejecutarPase() {
        DOM.btnPasar.disabled = true;
        try {
            const resp = await fetch('pasar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    codigo: estado.codigo,
                    token: estado.token
                })
            });
            const datos = await resp.json();

            if (datos.success) {
                anunciarPolite(datos.mensaje);
                await consultarEstado();
                setTimeout(consultarEstado, 900);
            } else {
                anunciarPolite(datos.error || 'No puedes pasar.');
            }
        } catch (err) {
            anunciarPolite('Error de comunicación al pasar.');
        }
    }

    function agregarLineaHistorial(texto) {
        const p = document.createElement('div');
        p.className = 'movimiento-linea';
        p.textContent = texto;
        DOM.registroMovimientos.appendChild(p);
    }

    // =========================================================================
    // ATAJOS DE TECLADO GLOBALES (M, T, R, P, J, Escape)
    // =========================================================================

    function configurarAtajosTeclado() {
        document.addEventListener('keydown', (e) => {
            // Ignorar atajos si el foco está en un campo de texto de formulario
            const tag = e.target.tagName.toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                return;
            }

            const tecla = e.key.toUpperCase();

            // Tecla Escape: Cerrar modales
            if (e.key === 'Escape') {
                if (!DOM.modalExtremo.classList.contains('oculto')) {
                    cerrarModalExtremo();
                    return;
                }
                if (!DOM.modalAtajos.classList.contains('oculto')) {
                    DOM.modalAtajos.classList.add('oculto');
                    DOM.btnAyudaAtajos.focus();
                    return;
                }
            }

            // Tecla T: Anunciar estado del tablero
            if (tecla === 'T') {
                e.preventDefault();
                anunciarEstadoTablero();
                return;
            }

            // Tecla R: Robar del pozo
            if (tecla === 'R') {
                e.preventDefault();
                if (!DOM.btnRobar.disabled && DOM.btnRobar.style.display !== 'none') {
                    ejecutarRobo();
                } else {
                    anunciarPolite("No puedes robar en este momento.");
                }
                return;
            }

            // Tecla P: Pasar
            if (tecla === 'P') {
                e.preventDefault();
                if (!DOM.btnPasar.disabled && DOM.btnPasar.style.display !== 'none') {
                    ejecutarPase();
                } else {
                    anunciarPolite("No puedes pasar en este momento.");
                }
                return;
            }

            // Tecla J: Anunciar jugadores en la mesa
            if (tecla === 'J') {
                e.preventDefault();
                anunciarResumenJugadores();
                return;
            }
        });
    }

    // =========================================================================
    // FIN DE PARTIDA
    // =========================================================================

    function renderizarFinPartida(datos) {
        if (estado.pollingTimer) {
            clearInterval(estado.pollingTimer);
            estado.pollingTimer = null;
        }

        const textoGanador = (estado.partida && estado.partida.ganador_texto) ? estado.partida.ganador_texto : "¡Partida finalizada!";
        const puntos = (estado.partida && estado.partida.puntos_ganador !== undefined) ? estado.partida.puntos_ganador : 0;

        DOM.resultadoFinCaja.innerHTML = `
            <p class="ganador-destacado">🏆 ${textoGanador}</p>
            <p><strong>Puntos acumulados en la ronda:</strong> ${puntos} puntos.</p>
        `;

        // 1. Renderizar tablero final con todas las fichas colocadas para estudio
        const tablero = (estado.partida && Array.isArray(estado.partida.tablero_fichas)) ? estado.partida.tablero_fichas : [];
        if (DOM.cuentaTableroFin) {
            DOM.cuentaTableroFin.textContent = String(tablero.length);
        }
        if (DOM.extremosTableroFin && estado.partida) {
            const izq = estado.partida.extremo_izq !== null ? nombrePalo(estado.partida.extremo_izq) : '-';
            const der = estado.partida.extremo_der !== null ? nombrePalo(estado.partida.extremo_der) : '-';
            DOM.extremosTableroFin.textContent = `Extremo izquierdo: ${izq} | Extremo derecho: ${der}`;
        }

        if (DOM.tableroFichasFin) {
            DOM.tableroFichasFin.innerHTML = '';
            if (tablero.length === 0) {
                DOM.tableroFichasFin.innerHTML = '<p class="texto-vacio">No hay fichas en el tablero.</p>';
            } else {
                tablero.forEach((f, idx) => {
                    const div = document.createElement('div');
                    const esDoble = (f[0] === f[1]);
                    div.className = `ficha-domino ficha-tablero ${esDoble ? 'doble' : ''}`;
                    div.setAttribute('role', 'img');
                    div.setAttribute('aria-label', `Ficha ${idx + 1} de ${tablero.length}: ${describirFicha(f)}`);
                    div.tabIndex = 0;

                    div.innerHTML = `
                        <div class="semicara">${f[0]}</div>
                        <div class="linea-division"></div>
                        <div class="semicara">${f[1]}</div>
                    `;
                    DOM.tableroFichasFin.appendChild(div);
                });
            }
        }

        // 2. Renderizar historial completo de jugadas para estudio
        if (DOM.registroMovimientosFin) {
            DOM.registroMovimientosFin.innerHTML = '';
            const listaHistorial = (datos && Array.isArray(datos.historial_completo) && datos.historial_completo.length > 0)
                ? datos.historial_completo
                : (datos && Array.isArray(datos.nuevos_movimientos) ? datos.nuevos_movimientos : []);

            if (listaHistorial.length === 0) {
                const lineasPrevias = DOM.registroMovimientos.querySelectorAll('.movimiento-linea');
                if (lineasPrevias.length > 0) {
                    lineasPrevias.forEach((linea, idx) => {
                        const p = document.createElement('div');
                        p.className = 'movimiento-linea';
                        p.textContent = `${idx + 1}. ${linea.textContent}`;
                        DOM.registroMovimientosFin.appendChild(p);
                    });
                } else if (estado.partida && estado.partida.ultimo_movimiento) {
                    const p = document.createElement('div');
                    p.className = 'movimiento-linea';
                    p.textContent = `1. ${estado.partida.ultimo_movimiento}`;
                    DOM.registroMovimientosFin.appendChild(p);
                }
            } else {
                listaHistorial.forEach((mov, idx) => {
                    const p = document.createElement('div');
                    p.className = 'movimiento-linea';
                    p.textContent = `${idx + 1}. ${mov.descripcion}`;
                    DOM.registroMovimientosFin.appendChild(p);
                });
            }
        }

        anunciarAssertive(`Fin de la partida. ${textoGanador}. Puedes estudiar a continuación el tablero con todas las fichas colocadas y la lista completa de jugadas.`);
        reproducirTono(523.25, 0.2); // Do5
        setTimeout(() => reproducirTono(659.25, 0.2), 200); // Mi5
        setTimeout(() => reproducirTono(783.99, 0.4), 400); // Sol5
    }

    // Ejecutar al cargar el DOM
    document.addEventListener('DOMContentLoaded', inicializar);

})();


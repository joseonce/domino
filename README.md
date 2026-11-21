# 🁢 Dominó para Todos

Juego de dominó online multijugador, completamente accesible y adaptado para lectores de pantalla (**NVDA**, **JAWS**, **VoiceOver** en iOS/macOS y **TalkBack** en Android), cumpliendo las pautas de accesibilidad **WCAG 2.1 AA**.

**Autor:** José Manuel Fernández Cortés  
**Web en producción:** [buscaapps.com/domino](https://buscaapps.com/domino)

---

## 🌟 Características Principales

### 1. Accesibilidad Universal Nativa (WCAG 2.1 AA)
- **Navegación completa por teclado:** Flechas izquierda/derecha para recorrer fichas, Enter/Espacio para jugar, teclas de acceso rápido (`T` para estado del tablero, `J` para lista de jugadores, `R` para robar, `P` para pasar).
- **Locuciones concisas y naturales:** Descripciones adaptadas al estado de la mesa sin palabras superfluas (*"Ficha 4 4, jugable por la derecha"*, *"Ficha blanca 2, jugable por ambos extremos"*). Nomenclatura tradicional española donde el 0 se denomina siempre **"blanca"**.
- **Respuesta instantánea sin pausas:** Confirmación inmediata con *"Ok"* tras cada jugada y cadencia fluida para la respuesta de los rivales.
- **Modo Alto Contraste:** Selector accesible con persistencia en navegador para personas con baja visión.

### 2. Ordenación Estratégica Continua de la Mano
Las fichas de los jugadores humanos se ordenan automáticamente según la doctrina clásica del dominó:
1. **Los dobles primero:** Priorizados por la fuerza de su palo (frecuencia en mano) y su valor.
2. **Acompañantes de los dobles:** Fichas que comparten número con los dobles en mano, agrupadas por el doble más fuerte.
3. **Números repetidos sin doble:** Series y palos fuertes de apoyo (ej. 2-3 y 2-4).
4. **Fichas huérfanas o solitarias:** Números aislados que no se repiten, reservados para la defensa final.

> **Reordenación dinámica continua:** Cada vez que se coloca una ficha o se roba del pozo, la mano recalcula al instante las frecuencias y se reordena automáticamente.

### 3. Regla Oficial de los 5 Dobles
De acuerdo con el reglamento de competición de la Federación de Dominó, si al repartir las fichas iniciales cualquier jugador (humano o máquina) recibe 5 o más dobles en su mano de 7 fichas, la mano se anula de forma automática, se anuncia la eventualidad por voz a todos los participantes y se vuelve a barajar y repartir.

### 4. Motor de Inteligencia Artificial (Máquinas Expertas)
Diseñado siguiendo las directrices estratégicas de la **Federación Española de Dominó (FED)** y los principios tácticos del maestro **Ignacio Zaibert**:
- **Conteo de Fichas Vivas:** Rastreo matemático de las 28 fichas en juego.
- **Mapeo de Fallas:** Castigo de los palos en los que los rivales han pasado.
- **Estrategia de Parejas:** Respeto y apertura del palo de salida del compañero, tapado del salidor contrario.
- **Táctica de Cuadratura y Tranque:** Cálculo probabilístico antes de forzar un cierre para asegurar la victoria por puntos.

### 5. Modalidades de Juego
- **2 Jugadores:** Mano de 7 fichas y pozo de 14 fichas.
- **3 Jugadores:** Mano de 7 fichas y pozo de 7 fichas.
- **4 Jugadores Individual:** 7 fichas por jugador, sin pozo.
- **4 Jugadores por Parejas:** Equipos alternos (Pareja 1 y Pareja 2) con puntuación compartida.

---

## 🛠️ Tecnologías Empleadas

- **Backend:** PHP 7.4+ / PHP 8.x con PDO MySQL.
- **Base de Datos:** MySQL / MariaDB (tablas `domino_partidas`, `domino_jugadores`, `domino_historial`).
- **Frontend:** HTML5 Semántico, CSS3 accesible (variables CSS, modo alto contraste), JavaScript Vanilla (ES6+) asíncrono con `aria-live`, `role="status"` y Web Audio API.

---

## 🚀 Instalación y Puesta en Marcha

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/joseonce/domino.git
   cd domino
   ```

2. **Configurar la base de datos:**
   - Copia `conexion.example.php` a `conexion.php`:
     ```bash
     cp conexion.example.php conexion.php
     ```
   - Edita `conexion.php` con tus credenciales de MySQL.

3. **Crear las tablas:**
   - Abre en el navegador `instalar.php` (ejemplo: `http://localhost/domino/instalar.php`) para inicializar automáticamente la base de datos.

4. **¡Listo para jugar!**
   - Accede a `index.html` para crear una sala o unirte a una partida en curso.

---

## 📄 Licencia

Desarrollado por **José Manuel Fernández Cortés**. Proyecto orientado a la accesibilidad e inclusión digital en juegos de mesa tradicionales.

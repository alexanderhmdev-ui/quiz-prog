# Battle Quiz — PvP + IA + ML

Proyecto educativo completo con **PHP MVC + MySQL/MariaDB + Java Spring Boot + Python Machine Learning**.

## Qué hace cada tecnología

- **PHP + JavaScript:** login por nickname, lobby, ranking, presencia online, cola de emparejamiento, sala PvP y coordinación de servicios.
- **MySQL/MariaDB:** usuarios, preguntas, opciones, historial, presencia online, cola PvP, duelos y respuestas.
- **Java Spring Boot (:8081):** modo individual contra IA, HP, daño, puntuación y preguntas por dificultad.
- **Python Flask + ML (:5000):** `RandomForestClassifier` para recomendar dificultad según precisión, tiempo, racha y rondas.

## Configuración de esta versión

```text
Apache: 81 / 443
MySQL: 8000
Base: battle_quiz
Usuario MySQL: root
Contraseña MySQL: 1-5
Java: 8081
Python ML: 5000
```

La conexión ya está configurada en:

```text
battle-quiz-php/config/database.php
```

## Si YA tienes la base battle_quiz creada

No la borres. En HeidiSQL abre y ejecuta solamente:

```text
battle-quiz-php/app/sql/matchmaking_upgrade.sql
```

Ese archivo agrega sin borrar tus datos:

- `presencia_usuarios`
- `cola_emparejamiento`
- `duelos_pvp`
- `duelo_preguntas`
- `duelo_respuestas`

También actualiza `partidas` para aceptar el modo `pvp` y el resultado `empate`.

## Si vas a instalar desde cero

Importa:

```text
battle-quiz-php/app/sql/database.sql
```

**Atención:** `database.sql` es para instalación limpia y reconstruye la base. Si ya tienes datos, usa `matchmaking_upgrade.sql`.

## Ejecutar el proyecto

Coloca la carpeta en:

```text
C:\laragon\www\Battle-Quiz
```

En Laragon inicia **Apache + MySQL**.

### Java

```powershell
cd C:\laragon\www\Battle-Quiz\battle-quiz-java
.\mvnw.cmd spring-boot:run
```

Debe responder en:

```text
http://localhost:8081/api/battle/health
```

### Python ML

Puedes usar:

```text
start-python.bat
```

O manualmente con tu Python 3.14:

```powershell
& C:\Users\alexa\AppData\Local\Python\pythoncore-3.14-64\python.exe -m pip install -r C:\laragon\www\Battle-Quiz\battle-quiz-python\requirements.txt
& C:\Users\alexa\AppData\Local\Python\pythoncore-3.14-64\python.exe C:\laragon\www\Battle-Quiz\battle-quiz-python\app.py
```

Debe responder en:

```text
http://localhost:5000/api/ml/health
```

### Web

Con Apache en el puerto 81:

```text
http://localhost:81/Battle-Quiz/battle-quiz-php/public/
```

Con ngrok:

```powershell
ngrok http 81
```

## Cómo probar el emparejamiento PvP

1. Ejecuta `matchmaking_upgrade.sql` en HeidiSQL.
2. Abre Battle Quiz en Chrome e ingresa con un nickname, por ejemplo `Alexander`.
3. Abre una ventana de incógnito, otro navegador o un segundo dispositivo.
4. Entra con otro nickname, por ejemplo `Jugador2`.
5. Ambos usuarios deben aparecer en **Jugadores conectados**.
6. Ambos pulsan **Buscar partida**.
7. El segundo jugador que entra a la cola empareja automáticamente con el primero.
8. Los dos son enviados a la misma sala PvP.
9. Ambos reciben el mismo conjunto de preguntas.
10. Cada respuesta correcta suma puntos y quita HP al rival.
11. El resultado se guarda en MySQL y actualiza el ranking.

Para probar desde dos dispositivos externos, ambos pueden abrir el mismo enlace público de ngrok. Cada dispositivo tendrá su propia sesión PHP.

## Flujo PvP

```text
Usuario A ─┐
           ├─> PHP ─> cola_emparejamiento ─> MySQL
Usuario B ─┘                         │
                                     ↓
                              crea duelos_pvp
                                     │
                                     ↓
                          mismas preguntas para ambos
                                     │
                                     ↓
                         respuestas + puntos + daño
                                     │
                                     ↓
                          historial + ranking MySQL
```

## Flujo IA/ML

1. PHP inicia la batalla individual.
2. Java selecciona y evalúa las preguntas.
3. PHP calcula precisión, tiempo y racha.
4. Python ML recomienda `easy`, `medium` o `hard`.
5. PHP comunica el nivel a Java.
6. Al finalizar, PHP guarda la partida en MySQL.

## Archivos nuevos del PvP

```text
battle-quiz-php/
├── app/controllers/MatchmakingController.php
├── app/models/MatchmakingModel.php
├── app/view/game/pvp.php
├── app/sql/matchmaking_upgrade.sql
├── public/lobby.js
└── public/pvp.js
```

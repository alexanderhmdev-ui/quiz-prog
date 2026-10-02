package com.battlequiz.services;

import com.battlequiz.models.Match;
import com.battlequiz.models.Question;
import org.springframework.stereotype.Service;

import java.util.*;
import java.util.stream.Collectors;

@Service
public class BattleService {
    private final Match match = new Match();
    private final Random random = new Random();
    private final List<Question> questions = buildQuestions();
    private Question currentQuestion;
    private int lastQuestionId = -1;

    public synchronized Map<String, Object> startBattle(String requestedDifficulty) {
        String difficulty = normalizeDifficulty(requestedDifficulty);
        match.reset(difficulty);
        currentQuestion = pickQuestion(difficulty);
        return buildState("¡Batalla iniciada! Nivel " + difficulty.toUpperCase() + ".", true);
    }

    public synchronized Map<String, Object> answer(int selectedIndex) {
        if (currentQuestion == null) return error("Primero debes iniciar una batalla.");
        if (match.isFinished()) return buildState("La batalla ya terminó. Inicia una nueva partida.", false);
        if (selectedIndex < 0 || selectedIndex >= currentQuestion.options().size()) return error("Respuesta inválida.");

        Question answered = currentQuestion;
        boolean correct = selectedIndex == answered.correctIndex();
        int damage;
        String message;

        if (correct) {
            match.getPlayer().incrementStreak();
            int baseDamage = switch (match.getDifficulty()) { case "easy" -> 20; case "hard" -> 13; default -> 16; };
            damage = baseDamage + random.nextInt(8) + Math.min(5, match.getPlayer().getStreak());
            int points = switch (answered.difficulty()) { case "hard" -> 180; case "easy" -> 80; default -> 120; };
            match.getEnemy().damage(damage);
            match.getPlayer().addScore(points);
            message = "¡Correcto! +" + points + " pts y " + damage + " de daño. " + answered.explanation();
        } else {
            match.getPlayer().resetStreak();
            int baseDamage = switch (match.getDifficulty()) { case "easy" -> 8; case "hard" -> 16; default -> 12; };
            damage = baseDamage + random.nextInt(7);
            match.getPlayer().damage(damage);
            message = "Incorrecto. La respuesta era “" + answered.options().get(answered.correctIndex()) + "”. " + answered.explanation();
        }

        if (match.getEnemy().getHp() <= 0 || match.getPlayer().getHp() <= 0) {
            match.setFinished(true);
            message = match.getEnemy().getHp() <= 0
                    ? "¡Victoria! Has derrotado al rival."
                    : "Derrota. La IA ganó esta batalla.";
        } else {
            match.nextRound();
            currentQuestion = pickQuestion(match.getDifficulty());
        }

        Map<String, Object> state = buildState(message, !match.isFinished());
        state.put("correct", correct);
        state.put("damage", damage);
        state.put("correctIndex", answered.correctIndex());
        state.put("explanation", answered.explanation());
        return state;
    }


    public synchronized Map<String, Object> adaptDifficulty(String requestedDifficulty) {
        if (match.isFinished()) return buildState("La batalla ya terminó.", false);
        String difficulty = normalizeDifficulty(requestedDifficulty);
        match.setDifficulty(difficulty);
        currentQuestion = pickQuestion(difficulty);
        Map<String, Object> state = buildState("Dificultad adaptada a " + difficulty.toUpperCase() + ".", true);
        state.put("adapted", true);
        return state;
    }

    public Map<String, Object> health() {
        return Map.of("status", "ok", "service", "battle-quiz-java", "questions", questions.size());
    }

    public List<Map<String, Object>> categories() {
        return questions.stream().collect(Collectors.groupingBy(Question::category, TreeMap::new, Collectors.counting()))
                .entrySet().stream().map(e -> Map.<String,Object>of("category", e.getKey(), "questions", e.getValue())).toList();
    }

    private Question pickQuestion(String difficulty) {
        List<Question> pool = questions.stream().filter(q -> q.difficulty().equals(difficulty)).toList();
        if (pool.isEmpty()) pool = questions;
        Question next;
        do { next = pool.get(random.nextInt(pool.size())); }
        while (pool.size() > 1 && next.id() == lastQuestionId);
        lastQuestionId = next.id();
        return next;
    }

    private Map<String, Object> buildState(String message, boolean includeQuestion) {
        Map<String, Object> response = new LinkedHashMap<>();
        response.put("status", "ok"); response.put("message", message);
        response.put("playerHp", match.getPlayer().getHp()); response.put("enemyHp", match.getEnemy().getHp());
        response.put("score", match.getPlayer().getScore()); response.put("round", match.getRound());
        response.put("streak", match.getPlayer().getStreak()); response.put("difficulty", match.getDifficulty());
        response.put("gameOver", match.isFinished());
        response.put("winner", match.isFinished() ? (match.getEnemy().getHp() <= 0 ? "player" : "enemy") : null);
        if (includeQuestion && currentQuestion != null) response.put("question", publicQuestion(currentQuestion));
        return response;
    }

    private Map<String,Object> publicQuestion(Question q) {
        Map<String,Object> out = new LinkedHashMap<>();
        out.put("id", q.id()); out.put("category", q.category()); out.put("text", q.text());
        out.put("options", q.options()); out.put("difficulty", q.difficulty()); return out;
    }

    private Map<String,Object> error(String message) { return Map.of("status","error","message",message); }
    private String normalizeDifficulty(String d) {
        return switch (d == null ? "" : d.toLowerCase(Locale.ROOT)) { case "easy","medium","hard" -> d.toLowerCase(Locale.ROOT); default -> "medium"; };
    }

    private List<Question> buildQuestions() {
        return List.of(
            q(1,"HTML","¿Qué etiqueta define el contenido principal visible de un documento?",List.of("<main>","<meta>","<link>","<head>"),0,"easy","<main> representa el contenido principal de la página."),
            q(2,"CSS","¿Qué declaración activa Flexbox?",List.of("display: flex","position: flex","layout: flex","flex: true"),0,"easy","Flexbox se activa con display: flex."),
            q(3,"JavaScript","¿Qué palabra permite declarar una variable reasignable?",List.of("let","const","final","define"),0,"easy","let crea una variable que puede reasignarse."),
            q(4,"PHP","¿Con qué símbolo empieza una variable PHP?",List.of("$","#","@","%"),0,"easy","Las variables PHP comienzan con $."),
            q(5,"MySQL","¿Qué sentencia consulta filas de una tabla?",List.of("SELECT","PULL","READ","FETCH TABLE"),0,"easy","SELECT obtiene datos de una o más tablas."),
            q(6,"Git","¿Qué comando inicializa un repositorio?",List.of("git init","git new","git start","git create"),0,"easy","git init crea la estructura .git."),
            q(7,"Java","¿Qué palabra clave crea una clase hija?",List.of("extends","inherits","superclass","instanceof"),0,"easy","extends indica herencia entre clases."),
            q(8,"HTTP","¿Qué código significa normalmente OK?",List.of("200","301","404","500"),0,"easy","200 indica una respuesta satisfactoria."),
            q(9,"POO","¿Qué concepto protege el estado interno mediante métodos de acceso?",List.of("Encapsulamiento","Recursión","Iteración","Sobrecarga"),0,"easy","El encapsulamiento controla el acceso a los atributos."),
            q(10,"API","¿Qué formato de texto es común en APIs REST?",List.of("JSON","PSD","EXE","BMP"),0,"easy","JSON es ligero y ampliamente usado para intercambio de datos."),

            q(11,"PHP","¿Qué operador concatena cadenas en PHP?",List.of(".","+","&","::"),0,"medium","PHP concatena strings con un punto."),
            q(12,"MySQL","¿Qué cláusula filtra grupos después de GROUP BY?",List.of("HAVING","WHERE GROUP","FILTER","AFTER"),0,"medium","HAVING filtra los resultados agregados."),
            q(13,"Java","¿Qué interfaz funcional recibe un valor y devuelve boolean?",List.of("Predicate","Consumer","Supplier","Runnable"),0,"medium","Predicate<T> representa una condición booleana."),
            q(14,"JavaScript","¿Qué método transforma cada elemento de un array y devuelve otro array?",List.of("map","forEach","some","find"),0,"medium","map devuelve un nuevo array con los resultados de la transformación."),
            q(15,"Git","¿Qué comando integra cambios de otra rama creando una combinación de historiales?",List.of("git merge","git add","git status","git clone"),0,"medium","git merge integra el historial de otra rama."),
            q(16,"CSS","¿Qué unidad es relativa al tamaño de fuente raíz?",List.of("rem","px","vh","cm"),0,"medium","rem toma como referencia font-size del elemento html."),
            q(17,"HTTP","¿Qué método suele usarse para crear un recurso REST?",List.of("POST","GET","HEAD","TRACE"),0,"medium","POST suele enviar datos para crear un recurso."),
            q(18,"SQL","¿Qué JOIN conserva todas las filas de la tabla izquierda?",List.of("LEFT JOIN","INNER JOIN","CROSS JOIN","SELF JOIN"),0,"medium","LEFT JOIN conserva toda la tabla izquierda aunque no haya coincidencia."),
            q(19,"POO","¿Qué permite que un mismo método tenga comportamientos distintos según el objeto?",List.of("Polimorfismo","Encapsulamiento","Normalización","Indexación"),0,"medium","El polimorfismo permite distintas implementaciones bajo una interfaz común."),
            q(20,"Web","¿Qué almacenamiento del navegador persiste sin fecha de expiración automática?",List.of("localStorage","sessionStorage","DOM","cache-control"),0,"medium","localStorage permanece hasta que se elimina explícitamente."),

            q(21,"Java","¿Qué colección evita duplicados por definición?",List.of("Set","List","ArrayList","Queue"),0,"hard","Set representa una colección sin elementos duplicados."),
            q(22,"MySQL","¿Qué nivel de normalización elimina dependencias transitivas?",List.of("3FN","1FN","2FN","BCNF siempre"),0,"hard","La tercera forma normal elimina dependencias transitivas de atributos no clave."),
            q(23,"PHP","¿Qué interfaz permite personalizar cómo un objeto se serializa a JSON?",List.of("JsonSerializable","SerializableJson","Arrayable","StringableJson"),0,"hard","JsonSerializable define jsonSerialize()."),
            q(24,"JavaScript","¿Qué cola procesa callbacks de Promise antes que timers pendientes?",List.of("Microtask queue","Render queue","I/O queue","Call stack"),0,"hard","Las Promises se programan como microtareas y se atienden antes que macrotareas como setTimeout."),
            q(25,"Git","¿Qué operación mueve commits a una nueva base reescribiendo historial?",List.of("rebase","fetch","stash","tag"),0,"hard","git rebase reaplica commits sobre otra base."),
            q(26,"HTTP","¿Qué código indica demasiadas solicitudes?",List.of("429","409","418","206"),0,"hard","HTTP 429 significa Too Many Requests."),
            q(27,"CSS","¿Qué función permite limitar un valor fluido entre mínimo y máximo?",List.of("clamp()","bound()","range()","limit()"),0,"hard","clamp(min, preferred, max) acota un valor CSS."),
            q(28,"SQL","¿Qué función de ventana numera filas dentro de una partición?",List.of("ROW_NUMBER()","COUNT_ROW()","RANK_ROW()","INDEX()"),0,"hard","ROW_NUMBER() asigna un número secuencial por partición y orden."),
            q(29,"Java","¿Qué palabra clave evita que un método sea sobrescrito?",List.of("final","static","sealed","const"),0,"hard","Un método final no puede ser sobrescrito por subclases."),
            q(30,"Arquitectura","¿Qué ventaja principal aporta separar PHP, Java y Python como servicios?",List.of("Separación de responsabilidades","Eliminar HTTP","Evitar bases de datos","No usar APIs"),0,"hard","Cada servicio puede especializarse y evolucionar con menor acoplamiento.")
        );
    }

    private Question q(int id,String cat,String text,List<String> opts,int correct,String difficulty,String explanation){
        return new Question(id,cat,text,opts,correct,difficulty,explanation);
    }
}

const $ = (id) => document.getElementById(id);
const startButton = $('startButton');
const restartButton = $('restartButton');
const startPanel = $('startPanel');
const questionPanel = $('questionPanel');
const endPanel = $('endPanel');
const answersGrid = $('answersGrid');
const questionText = $('questionText');
const categoryBadge = $('categoryBadge');
const difficultyBadge = $('difficultyBadge');
const feedback = $('feedback');
const playerHp = $('playerHp');
const enemyHp = $('enemyHp');
const playerHpBar = $('playerHpBar');
const enemyHpBar = $('enemyHpBar');
const scoreValue = $('scoreValue');
const roundValue = $('roundValue');
const accuracyValue = $('accuracyValue');
const endTitle = $('endTitle');
const endText = $('endText');
const mlSummary = $('mlSummary');
const mlDifficultySide = $('mlDifficultySide');
const timerLabel = $('timerLabel');

let selectedDifficulty = 'adaptive';
let questionStartedAt = 0;
let timerId = null;

document.querySelectorAll('.difficulty-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.difficulty-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedDifficulty = btn.dataset.difficulty;
    });
});

startButton?.addEventListener('click', startBattle);
restartButton?.addEventListener('click', startBattle);

async function api(action, options = {}, params = {}) {
    const query = new URLSearchParams({ action, ...params });
    const response = await fetch(`index.php?${query.toString()}`, options);
    let data;
    try { data = await response.json(); }
    catch { throw new Error('El servidor devolvió una respuesta no válida.'); }
    if (!response.ok || data.status === 'error') throw new Error(data.message || 'Error de servidor');
    return data;
}

async function startBattle() {
    setBusy(true);
    stopTimer();
    try {
        const data = await api('startBattle', {}, { difficulty: selectedDifficulty });
        startPanel.classList.add('hidden');
        endPanel.classList.add('hidden');
        questionPanel.classList.remove('hidden');
        feedback.className = 'feedback neutral';
        feedback.textContent = data.message || '¡Batalla iniciada!';
        updateState(data);
        renderQuestion(data.question);
        if (mlDifficultySide) mlDifficultySide.textContent = capitalize(data.mlDifficulty || 'medium');
    } catch (error) {
        showError(error.message);
    } finally {
        setBusy(false);
    }
}

async function submitAnswer(index) {
    disableAnswers(true);
    const responseTime = Math.max(0.2, (performance.now() - questionStartedAt) / 1000);
    stopTimer();
    try {
        const data = await api('answer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ answer: index, responseTime })
        });

        const buttons = [...answersGrid.querySelectorAll('.answer-btn')];
        if (data.correct) {
            buttons[index]?.classList.add('correct');
            feedback.className = 'feedback good';
        } else {
            buttons[index]?.classList.add('wrong');
            if (Number.isInteger(data.correctIndex)) buttons[data.correctIndex]?.classList.add('correct');
            feedback.className = 'feedback bad';
        }

        feedback.textContent = data.message;
        updateState(data);
        if (data.ml) {
            accuracyValue.textContent = `${data.ml.accuracy}%`;
            if (mlDifficultySide) mlDifficultySide.textContent = capitalize(data.ml.difficulty);
        }

        if (data.gameOver) {
            setTimeout(() => showEnd(data), 700);
        } else {
            setTimeout(() => {
                renderQuestion(data.question);
                disableAnswers(false);
            }, 900);
        }
    } catch (error) {
        showError(error.message);
        disableAnswers(false);
    }
}

function renderQuestion(question) {
    if (!question) return;
    categoryBadge.textContent = question.category || 'General';
    difficultyBadge.textContent = capitalize(question.difficulty || 'medium');
    difficultyBadge.dataset.level = question.difficulty || 'medium';
    questionText.textContent = question.text || 'Pregunta';
    answersGrid.innerHTML = '';
    (question.options || []).forEach((option, index) => {
        const button = document.createElement('button');
        button.className = 'answer-btn';
        button.innerHTML = `<span class="option-key">${String.fromCharCode(65 + index)}</span><span>${escapeHtml(option)}</span>`;
        button.addEventListener('click', () => submitAnswer(index));
        answersGrid.appendChild(button);
    });
    startTimer();
}

function updateState(data) {
    const pHp = Math.max(0, Number(data.playerHp ?? 100));
    const eHp = Math.max(0, Number(data.enemyHp ?? 100));
    playerHp.textContent = pHp;
    enemyHp.textContent = eHp;
    playerHpBar.style.width = `${pHp}%`;
    enemyHpBar.style.width = `${eHp}%`;
    scoreValue.textContent = data.score ?? 0;
    roundValue.textContent = data.round ?? 1;
    if (data.message) feedback.textContent = data.message;
}

function showEnd(data) {
    stopTimer();
    questionPanel.classList.add('hidden');
    endPanel.classList.remove('hidden');
    if (data.winner === 'player') {
        endTitle.textContent = '¡Victoria en la arena!';
        endText.textContent = `Terminaste con ${data.score} puntos después de ${data.round} rondas.`;
    } else {
        endTitle.textContent = 'La IA ganó esta ronda';
        endText.textContent = `Conseguiste ${data.score} puntos. Vuelve a intentarlo y mejora tu precisión.`;
    }
    const ml = data.ml || {};
    mlSummary.innerHTML = `
        <div><span>Precisión</span><strong>${ml.accuracy ?? 0}%</strong></div>
        <div><span>Tiempo prom.</span><strong>${ml.avgResponseTime ?? '-'} s</strong></div>
        <div><span>Siguiente nivel</span><strong>${capitalize(ml.difficulty || 'medium')}</strong></div>`;
}

function startTimer() {
    stopTimer();
    questionStartedAt = performance.now();
    timerId = setInterval(() => {
        const seconds = (performance.now() - questionStartedAt) / 1000;
        if (timerLabel) timerLabel.textContent = `${seconds.toFixed(1)} s`;
    }, 100);
}

function stopTimer() {
    if (timerId) clearInterval(timerId);
    timerId = null;
}

function disableAnswers(disabled) {
    answersGrid.querySelectorAll('button').forEach(btn => btn.disabled = disabled);
}
function setBusy(busy) {
    if (startButton) startButton.disabled = busy;
    if (restartButton) restartButton.disabled = busy;
}
function showError(message) {
    feedback.className = 'feedback bad';
    feedback.textContent = message;
}
function capitalize(value) {
    const v = String(value || '');
    return v.charAt(0).toUpperCase() + v.slice(1);
}
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

const byId = (id) => document.getElementById(id);
const matchId = Number(document.body.dataset.matchId || 0);
const questionPanel = byId('questionPanel');
const waitingPanel = byId('waitingPanel');
const answersGrid = byId('answersGrid');
const questionText = byId('questionText');
const categoryBadge = byId('categoryBadge');
const difficultyBadge = byId('difficultyBadge');
const feedback = byId('feedback');
const timerLabel = byId('timerLabel');
const endPanel = byId('endPanel');
const abandonButton = byId('abandonButton');

let currentQuestionId = null;
let questionStartedAt = performance.now();
let timerId = null;
let pollId = null;
let submitting = false;
let finished = false;

async function api(action, options = {}, params = {}) {
    const query = new URLSearchParams({ action, ...params });
    const response = await fetch(`index.php?${query}`, options);
    let data;
    try { data = await response.json(); }
    catch { throw new Error('Respuesta inválida del servidor.'); }
    if (!response.ok || data.status === 'error') throw new Error(data.message || 'Error de servidor');
    return data;
}

function initials(name) {
    return String(name || '?').trim().slice(0, 2).toUpperCase();
}

function updateState(state, allowQuestionRender = true) {
    if (!state) return;
    const self = state.self || {};
    const opponent = state.opponent || {};
    const total = Number(state.totalQuestions || 0);

    byId('selfName').textContent = self.nickname || 'Jugador';
    byId('opponentName').textContent = opponent.nickname || 'Rival';
    byId('selfAvatar').textContent = initials(self.nickname);
    byId('opponentAvatar').textContent = initials(opponent.nickname);
    byId('playerHp').textContent = self.hp ?? 100;
    byId('enemyHp').textContent = opponent.hp ?? 100;
    byId('playerHpBar').style.width = `${Math.max(0, Math.min(100, Number(self.hp ?? 100)))}%`;
    byId('enemyHpBar').style.width = `${Math.max(0, Math.min(100, Number(opponent.hp ?? 100)))}%`;
    byId('scoreValue').textContent = self.score ?? 0;
    byId('opponentScoreValue').textContent = opponent.score ?? 0;
    byId('selfAnswered').textContent = self.answered ?? 0;
    byId('opponentAnswered').textContent = opponent.answered ?? 0;
    byId('waitingOpponentName').textContent = opponent.nickname || 'tu rival';

    if (state.finished) {
        showEnd(state);
        return;
    }

    if (state.question && allowQuestionRender && Number(state.question.id) !== currentQuestionId) {
        renderQuestion(state.question, total);
    }

    const waiting = !!state.waitingOpponent && !state.finished;
    waitingPanel?.classList.toggle('hidden', !waiting);
    questionPanel?.classList.toggle('hidden', waiting);
    if (waiting) {
        stopTimer();
        byId('roundValue').textContent = `${self.answered || total}/${total}`;
        feedback.className = 'feedback neutral';
        feedback.textContent = `Esperando a ${opponent.nickname || 'tu rival'}...`;
    }
}

function renderQuestion(question, total) {
    currentQuestionId = Number(question.id);
    questionStartedAt = performance.now();
    categoryBadge.textContent = question.category || 'General';
    difficultyBadge.textContent = difficultyLabel(question.difficulty);
    difficultyBadge.dataset.level = question.difficulty || 'medio';
    questionText.textContent = question.text || 'Pregunta';
    byId('roundValue').textContent = `${question.position || 1}/${total || '?'}`;
    answersGrid.innerHTML = '';

    (question.options || []).forEach((option, index) => {
        const button = document.createElement('button');
        button.className = 'answer-btn';
        button.dataset.optionId = option.id;
        button.innerHTML = `<span class="option-key">${String.fromCharCode(65 + index)}</span><span>${escapeHtml(option.text)}</span>`;
        button.addEventListener('click', () => submitAnswer(Number(option.id), button));
        answersGrid.appendChild(button);
    });

    feedback.className = 'feedback neutral';
    feedback.textContent = 'Elige una respuesta. Tu rival está resolviendo el mismo duelo.';
    startTimer();
}

async function submitAnswer(optionId, clickedButton) {
    if (submitting || finished) return;
    submitting = true;
    disableAnswers(true);
    const responseMs = Math.max(100, Math.round(performance.now() - questionStartedAt));
    stopTimer();

    try {
        const data = await api('pvpAnswer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ matchId, optionId, responseMs })
        });
        const answer = data.answerFeedback || {};
        if (answer.correct) {
            clickedButton?.classList.add('correct');
            feedback.className = 'feedback good';
            feedback.textContent = `¡Correcto! +${answer.points || 0} pts · ${answer.damage || 0} de daño. ${answer.explanation || ''}`.trim();
        } else {
            clickedButton?.classList.add('wrong');
            feedback.className = 'feedback bad';
            feedback.textContent = `Respuesta incorrecta. ${answer.explanation || ''}`.trim();
        }
        updateState(data, false);
        setTimeout(() => {
            submitting = false;
            updateState(data, true);
            if (!data.finished && data.question && Number(data.question.id) === currentQuestionId) {
                // El estado devuelto ya apunta a la siguiente pregunta únicamente cuando cambió.
                fetchState();
            }
        }, 900);
    } catch (error) {
        feedback.className = 'feedback bad';
        feedback.textContent = error.message;
        submitting = false;
        disableAnswers(false);
        startTimer();
    }
}

async function fetchState() {
    if (finished || submitting) return;
    try {
        const data = await api('pvpState', {}, { match: matchId });
        updateState(data, true);
    } catch (error) {
        feedback.className = 'feedback bad';
        feedback.textContent = error.message;
    }
}

function showEnd(state) {
    if (finished) return;
    finished = true;
    stopTimer();
    if (pollId) clearInterval(pollId);
    questionPanel?.classList.add('hidden');
    waitingPanel?.classList.add('hidden');
    endPanel?.classList.remove('hidden');
    abandonButton?.classList.add('hidden');

    const self = state.self || {};
    const opponent = state.opponent || {};
    const winner = state.match?.winner;
    const title = byId('endTitle');
    const text = byId('endText');
    const icon = byId('endIcon');

    if (winner === 'self') {
        title.textContent = '¡Victoria PvP!';
        text.textContent = `Derrotaste a ${opponent.nickname || 'tu rival'} con ${self.score || 0} puntos.`;
        icon.textContent = '🏆';
    } else if (winner === 'opponent') {
        title.textContent = 'Duelo finalizado';
        text.textContent = `${opponent.nickname || 'Tu rival'} ganó esta partida. Tu puntuación fue ${self.score || 0}.`;
        icon.textContent = '⚔';
    } else {
        title.textContent = '¡Empate!';
        text.textContent = `Ambos terminaron igualados. Marcador: ${self.score || 0} - ${opponent.score || 0}.`;
        icon.textContent = '🤝';
    }

    byId('endSelfScore').textContent = self.score ?? 0;
    byId('endSelfHp').textContent = self.hp ?? 0;
    byId('endOpponentScore').textContent = opponent.score ?? 0;
    feedback.className = 'feedback neutral';
    feedback.textContent = 'El resultado ya fue guardado en tu historial.';
}

async function abandonMatch() {
    if (finished) return;
    if (!confirm('¿Seguro que quieres abandonar? El rival ganará la partida.')) return;
    try {
        const data = await api('pvpAbandon', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ matchId })
        });
        updateState(data, true);
    } catch (error) {
        feedback.className = 'feedback bad';
        feedback.textContent = error.message;
    }
}

function startTimer() {
    stopTimer();
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
    answersGrid?.querySelectorAll('button').forEach(btn => btn.disabled = disabled);
}

function difficultyLabel(value) {
    return ({ facil: 'Fácil', medio: 'Medio', dificil: 'Difícil' })[value] || value || 'Medio';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

abandonButton?.addEventListener('click', abandonMatch);

updateState(window.BQ_INITIAL_PVP_STATE || {}, true);
pollId = setInterval(fetchState, 1800);

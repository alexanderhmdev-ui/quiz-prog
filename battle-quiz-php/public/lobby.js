const q = (id) => document.getElementById(id);
const queueButton = q('queueButton');
const quickMatchHero = q('quickMatchHero');
const cancelQueueButton = q('cancelQueueButton');
const idlePanel = q('matchmakingIdle');
const searchingPanel = q('matchmakingSearching');
const foundPanel = q('matchmakingFound');
const foundOpponent = q('foundOpponent');
const onlineUsersList = q('onlineUsersList');
const onlineCount = q('onlineCount');
const refreshPlayers = q('refreshPlayers');

let searching = false;
let redirecting = false;
let heartbeatTimer = null;

async function api(action, options = {}, params = {}) {
    const query = new URLSearchParams({ action, ...params });
    const response = await fetch(`index.php?${query}`, options);
    let data;
    try { data = await response.json(); }
    catch { throw new Error('El servidor devolvió una respuesta inválida.'); }
    if (!response.ok || data.status === 'error') throw new Error(data.message || 'Error de servidor');
    return data;
}

function setMode(mode) {
    idlePanel?.classList.toggle('hidden', mode !== 'idle');
    searchingPanel?.classList.toggle('hidden', mode !== 'searching');
    foundPanel?.classList.toggle('hidden', mode !== 'found');
    searching = mode === 'searching';
}

async function joinQueue() {
    if (!window.BQ_DB_AVAILABLE || redirecting) return;
    setMode('searching');
    try {
        const data = await api('joinQueue', { method: 'POST' });
        handleQueueState(data);
    } catch (error) {
        setMode('idle');
        alert(error.message);
    }
}

async function cancelQueue() {
    try {
        await api('leaveQueue', { method: 'POST' });
    } catch (_) {}
    setMode('idle');
}

function handleQueueState(queue) {
    if (!queue) return;
    if (queue.queueStatus === 'matched' && queue.matchId && !redirecting) {
        redirecting = true;
        setMode('found');
        const name = queue.opponent?.nickname || 'Rival';
        if (foundOpponent) foundOpponent.textContent = `${name} está listo. Cargando duelo #${queue.matchId}...`;
        setTimeout(() => {
            window.location.href = `index.php?action=pvp&match=${encodeURIComponent(queue.matchId)}`;
        }, 950);
        return;
    }
    setMode(queue.queueStatus === 'waiting' ? 'searching' : 'idle');
}

function renderOnlineUsers(users = []) {
    if (!onlineUsersList) return;
    if (!users.length) {
        onlineUsersList.innerHTML = `<div class="empty-online"><span class="pulse-dot"></span><p>No hay otros usuarios activos todavía. Abre el enlace en otro navegador o dispositivo para probar el PvP.</p></div>`;
        return;
    }
    onlineUsersList.innerHTML = users.map(user => {
        const stateText = user.estado === 'queue' ? 'Buscando partida' : user.estado === 'battle' ? 'En batalla' : 'Disponible';
        const stateClass = user.estado === 'queue' ? 'queue' : user.estado === 'battle' ? 'battle' : 'online';
        const initials = escapeHtml(String(user.nickname || '?').slice(0, 2).toUpperCase());
        return `<div class="online-user-row">
            <div class="online-avatar">${initials}</div>
            <div class="online-user-info"><strong>${escapeHtml(user.nickname)}</strong><span class="presence-state ${stateClass}">${stateText}</span></div>
            <b>${Number(user.puntos_totales || 0)} pts</b>
        </div>`;
    }).join('');
}

async function heartbeat() {
    if (!window.BQ_DB_AVAILABLE || redirecting) return;
    try {
        const data = await api('heartbeat');
        if (onlineCount) onlineCount.textContent = data.onlineCount ?? 1;
        renderOnlineUsers(data.onlineUsers || []);
        handleQueueState(data.queue);
    } catch (error) {
        if (onlineUsersList) {
            onlineUsersList.innerHTML = `<div class="feedback bad">${escapeHtml(error.message)}</div>`;
        }
    }
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

queueButton?.addEventListener('click', joinQueue);
quickMatchHero?.addEventListener('click', joinQueue);
cancelQueueButton?.addEventListener('click', cancelQueue);
refreshPlayers?.addEventListener('click', heartbeat);

if (window.BQ_DB_AVAILABLE) {
    heartbeat();
    heartbeatTimer = setInterval(heartbeat, 3000);
}

window.addEventListener('beforeunload', () => {
    if (heartbeatTimer) clearInterval(heartbeatTimer);
});

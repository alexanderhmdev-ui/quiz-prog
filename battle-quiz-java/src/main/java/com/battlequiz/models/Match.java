package com.battlequiz.models;

public class Match {
    private final Player player = new Player("Jugador");
    private final Player enemy = new Player("Rival IA");
    private int round;
    private boolean finished;
    private String difficulty;

    public Match() { reset("medium"); }

    public void reset(String difficulty) {
        player.reset(); enemy.reset(); round = 1; finished = false;
        this.difficulty = difficulty == null ? "medium" : difficulty;
    }
    public Player getPlayer() { return player; }
    public Player getEnemy() { return enemy; }
    public int getRound() { return round; }
    public boolean isFinished() { return finished; }
    public String getDifficulty() { return difficulty; }
    public void setDifficulty(String difficulty) { this.difficulty = difficulty; }
    public void nextRound() { round++; }
    public void setFinished(boolean finished) { this.finished = finished; }
}

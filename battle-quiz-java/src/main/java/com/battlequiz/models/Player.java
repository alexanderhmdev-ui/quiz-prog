package com.battlequiz.models;

public class Player {
    private final String name;
    private int hp;
    private int score;
    private int streak;

    public Player(String name) {
        this.name = name;
        reset();
    }

    public void reset() { hp = 100; score = 0; streak = 0; }
    public String getName() { return name; }
    public int getHp() { return hp; }
    public int getScore() { return score; }
    public int getStreak() { return streak; }
    public void damage(int amount) { hp = Math.max(0, hp - Math.max(0, amount)); }
    public void addScore(int points) { score += Math.max(0, points); }
    public void incrementStreak() { streak++; }
    public void resetStreak() { streak = 0; }
}

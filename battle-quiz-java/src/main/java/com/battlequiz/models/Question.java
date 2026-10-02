package com.battlequiz.models;

import java.util.List;

public record Question(
        int id,
        String category,
        String text,
        List<String> options,
        int correctIndex,
        String difficulty,
        String explanation
) {}

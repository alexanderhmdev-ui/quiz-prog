package com.battlequiz.controllers;

import com.battlequiz.services.BattleService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/battle")
@CrossOrigin(origins = "*")
public class BattleController {
    private final BattleService battleService;
    public BattleController(BattleService battleService) { this.battleService = battleService; }

    @GetMapping("/health")
    public Map<String,Object> health() { return battleService.health(); }

    @GetMapping("/categories")
    public List<Map<String,Object>> categories() { return battleService.categories(); }

    @GetMapping("/start")
    public Map<String,Object> startBattle(@RequestParam(defaultValue = "medium") String difficulty) {
        return battleService.startBattle(difficulty);
    }


    @PostMapping("/adapt")
    public ResponseEntity<Map<String,Object>> adapt(@RequestBody Map<String,String> payload) {
        String difficulty = payload.getOrDefault("difficulty", "medium");
        return ResponseEntity.ok(battleService.adaptDifficulty(difficulty));
    }

    @PostMapping("/answer")
    public ResponseEntity<Map<String,Object>> answer(@RequestBody Map<String,Integer> payload) {
        Integer answer = payload.get("answer");
        if (answer == null || answer < 0 || answer > 3) {
            return ResponseEntity.badRequest().body(Map.of("status","error","message","Respuesta inválida"));
        }
        Map<String,Object> result = battleService.answer(answer);
        return "error".equals(result.get("status")) ? ResponseEntity.badRequest().body(result) : ResponseEntity.ok(result);
    }
}

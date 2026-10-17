-- Importance d'une tâche : Mineure (défaut), Majeure ou Critique.

ALTER TABLE task
    ADD COLUMN importance VARCHAR(10) NOT NULL DEFAULT 'Mineure'
    CHECK (importance IN ('Mineure', 'Majeure', 'Critique'));

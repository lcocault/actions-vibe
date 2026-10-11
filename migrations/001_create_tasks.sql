-- Schéma initial : tâches et dépendances (actions requises).

CREATE TABLE task (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title            VARCHAR(255) NOT NULL CHECK (btrim(title) <> ''),
    category         VARCHAR(10)  NOT NULL CHECK (category IN ('Pro', 'Perso')),
    due_date         DATE,
    recurrence       VARCHAR(20)  CHECK (recurrence IN ('quotidienne', 'hebdomadaire', 'mensuelle', 'annuelle')),
    duration_minutes INTEGER      CHECK (duration_minutes > 0),
    description      TEXT,
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX task_due_date_idx ON task (due_date);
CREATE INDEX task_category_idx ON task (category);

-- La tâche `task_id` requiert que `required_task_id` soit réalisée.
CREATE TABLE task_dependency (
    task_id          BIGINT NOT NULL REFERENCES task (id) ON DELETE CASCADE,
    required_task_id BIGINT NOT NULL REFERENCES task (id) ON DELETE CASCADE,
    PRIMARY KEY (task_id, required_task_id),
    CHECK (task_id <> required_task_id)
);

CREATE INDEX task_dependency_required_idx ON task_dependency (required_task_id);

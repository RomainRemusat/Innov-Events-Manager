-- Rattache uniquement les devis dont l'événement est identifié sans ambiguïté.
-- Les contrôles portent sur le client propriétaire et la date du projet.
START TRANSACTION;

CREATE TEMPORARY TABLE quote_event_links (
    quote_id INT PRIMARY KEY,
    event_id INT NOT NULL
);

INSERT INTO quote_event_links (quote_id, event_id) VALUES
    (2, 2),
    (3, 3),
    (33, 33),
    (37, 37),
    (44, 44),
    (45, 45),
    (46, 46),
    (58, 58),
    (59, 59),
    (60, 60),
    (61, 61),
    (62, 62),
    (69, 69),
    (70, 70),
    (73, 73),
    (74, 74),
    (77, 77),
    (81, 81),
    (82, 82),
    (85, 85),
    (86, 86),
    (89, 89),
    (90, 90),
    (93, 93),
    (94, 94),
    (98, 98),
    (99, 99),
    (103, 103),
    (108, 108),
    (112, 112),
    (113, 113),
    (117, 117),
    (118, 118),
    (122, 122);

UPDATE devis d
INNER JOIN quote_event_links links ON links.quote_id = d.id_devis
INNER JOIN prospects p ON p.id = d.id_prospect
INNER JOIN events e ON e.id = links.event_id
SET d.event_id = e.id
WHERE d.event_id IS NULL
  AND e.client_id = p.user_id
  AND DATE(e.start_date) = p.event_date;

SELECT ROW_COUNT() AS repaired_quote_event_links;

DROP TEMPORARY TABLE quote_event_links;
COMMIT;

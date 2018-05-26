
class YAAMP_OBJECT
{
public:
	int id;
	int lock_count;

	bool unlock;
	bool deleted;
};

typedef void (*YAAMP_OBJECT_DELETE_FUNC)(YAAMP_OBJECT *);

YAAMP_OBJECT *object_find(CommonList *list, int id, bool lock=false);
void object_prune(CommonList *list, YAAMP_OBJECT_DELETE_FUNC deletefunc);
void object_prune_debug(CommonList *list, YAAMP_OBJECT_DELETE_FUNC deletefunc);

void object_lock(YAAMP_OBJECT *object);
void object_unlock(YAAMP_OBJECT *object);

void object_delete(YAAMP_OBJECT *object);






import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { TaskService } from './task.service';
import { ApiConfigService } from '../../../core/services/api-config.service';

describe('TaskService', () => {
  let service: TaskService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        TaskService,
        {
          provide: ApiConfigService,
          useValue: { apiUrl: 'http://localhost:8000/api/v1' },
        },
      ],
    });

    service = TestBed.inject(TaskService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should fetch tasks', () => {
    const mockTasks = [
      { id: 1, title: 'Test', completed: false, created_at: '2026-07-01T00:00:00Z' },
    ];

    service.getTasks().subscribe((tasks) => {
      expect(tasks).toEqual(mockTasks);
    });

    const req = httpMock.expectOne('http://localhost:8000/api/v1/tarefas');
    expect(req.request.method).toBe('GET');
    req.flush(mockTasks);
  });

  it('should create a task', () => {
    const mockTask = {
      id: 2,
      title: 'Nova',
      completed: false,
      created_at: '2026-07-01T00:00:00Z',
    };

    service.createTask({ title: 'Nova' }).subscribe((task) => {
      expect(task).toEqual(mockTask);
    });

    const req = httpMock.expectOne('http://localhost:8000/api/v1/tarefas');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ title: 'Nova' });
    req.flush(mockTask);
  });

  it('should delete a task', () => {
    service.deleteTask(1).subscribe((result) => {
      expect(result).toBeNull();
    });

    const req = httpMock.expectOne('http://localhost:8000/api/v1/tarefas/1');
    expect(req.request.method).toBe('DELETE');
    req.flush(null);
  });
});

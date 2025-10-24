const ReportService = require('../services/reportService');
const RoomService = require('../services/roomService');
const ResponseHelper = require('../utils/responseHelper');
const Logger = require('../utils/logger');
const { sequelize } = require('../config/database');

/**
 * Verificar que el supervisor tiene acceso a la sala
 */
async function verifySupervisorRoomAccess(supervisorId, roomId) {
  try {
    // Los supervisores (role 3) pueden ver todas las salas
    // O verificar asignaciones específicas si tu sistema lo requiere
    const [assignment] = await sequelize.query(`
      SELECT 1
      FROM agent_assignments
      WHERE agent_id = $1 
      AND room_id = $2
      AND status = 'active'
    `, {
      bind: [supervisorId, roomId],
      type: sequelize.QueryTypes.SELECT
    });

    // Si no hay asignación específica, verificar si es supervisor general
    if (!assignment) {
      const [user] = await sequelize.query(`
        SELECT role FROM "user" WHERE id = $1
      `, {
        bind: [supervisorId],
        type: sequelize.QueryTypes.SELECT
      });

      // Supervisores (role 3) tienen acceso a todas las salas
      return user?.role === 3 || user?.role === 4;
    }

    return true;

  } catch (error) {
    Logger.error('Error verificando acceso de supervisor', error);
    return false;
  }
}

/**
 * Métricas de sesiones para una sala específica
 */
async function getRoomSessionMetrics(roomId, targetDate) {
  try {
    const [metrics] = await sequelize.query(`
      SELECT 
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sessions,
        COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned_sessions,
        COUNT(CASE WHEN agent_id IS NOT NULL THEN 1 END) as sessions_with_agent,
        AVG(
          CASE 
            WHEN ended_at IS NOT NULL AND started_at IS NOT NULL THEN 
              EXTRACT(EPOCH FROM (ended_at - started_at))/60 
            ELSE NULL 
          END
        ) as avg_duration
      FROM chat_sessions
      WHERE (room_uuid::text = $1 OR room_id = $1)
      AND DATE(created_at) = $2
    `, {
      bind: [roomId, targetDate],
      type: sequelize.QueryTypes.SELECT
    });

    const totalSessions = parseInt(metrics.total_sessions) || 0;
    const sessionsWithAgent = parseInt(metrics.sessions_with_agent) || 0;
    const abandonedSessions = parseInt(metrics.abandoned_sessions) || 0;

    return {
      total_sessions: totalSessions,
      active_sessions: parseInt(metrics.active_sessions) || 0,
      waiting_sessions: parseInt(metrics.waiting_sessions) || 0,
      completed_sessions: parseInt(metrics.completed_sessions) || 0,
      abandoned_sessions: abandonedSessions,
      sessions_with_agent: sessionsWithAgent,
      avg_duration: parseFloat(metrics.avg_duration) || 0,
      attendance_rate: totalSessions > 0 ? Math.round((sessionsWithAgent / totalSessions) * 100) : 0,
      abandonment_rate: totalSessions > 0 ? Math.round((abandonedSessions / totalSessions) * 100) : 0
    };

  } catch (error) {
    Logger.error('Error obteniendo métricas de sesiones de sala', error);
    return {};
  }
}

/**
 * Métricas de agentes para una sala específica
 */
async function getRoomAgentMetrics(roomId, targetDate) {
  try {
    // Agentes asignados a esta sala
    const [agentStats] = await sequelize.query(`
      SELECT 
        COUNT(DISTINCT aa.agent_id) as total_assigned,
        COUNT(DISTINCT CASE WHEN u.is_active = true THEN aa.agent_id END) as currently_active,
        COUNT(DISTINCT CASE 
          WHEN u.is_active = true AND u.disponibilidad = 'presente' 
          THEN aa.agent_id 
        END) as available_now
      FROM agent_assignments aa
      JOIN "user" u ON aa.agent_id = u.id
      WHERE aa.room_id = $1 
      AND aa.status = 'active'
      AND u.role = 2
    `, {
      bind: [roomId],
      type: sequelize.QueryTypes.SELECT
    });

    // Agentes trabajando actualmente en esta sala
    const [activeAgents] = await sequelize.query(`
      SELECT 
        COUNT(DISTINCT agent_id) as on_session
      FROM chat_sessions
      WHERE (room_uuid::text = $1 OR room_id = $1)
      AND status = 'active'
      AND agent_id IS NOT NULL
    `, {
      bind: [roomId],
      type: sequelize.QueryTypes.SELECT
    });

    // Lista de agentes con sus métricas
    const agentList = await sequelize.query(`
      SELECT 
        u.id,
        u.name,
        u.email,
        u.disponibilidad,
        COUNT(DISTINCT s.id) as sessions_today,
        aa.is_primary_agent,
        aa.max_concurrent_chats
      FROM agent_assignments aa
      JOIN "user" u ON aa.agent_id = u.id
      LEFT JOIN chat_sessions s ON s.agent_id = u.id 
        AND (s.room_uuid::text = $1 OR s.room_id = $1)
        AND DATE(s.created_at) = $2
      WHERE aa.room_id = $1
      AND aa.status = 'active'
      AND u.role = 2
      GROUP BY u.id, u.name, u.email, u.disponibilidad, aa.is_primary_agent, aa.max_concurrent_chats
      ORDER BY sessions_today DESC
    `, {
      bind: [roomId, targetDate],
      type: sequelize.QueryTypes.SELECT
    });

    const totalAssigned = parseInt(agentStats.total_assigned) || 0;
    const currentlyActive = parseInt(agentStats.currently_active) || 0;

    return {
      total_assigned: totalAssigned,
      currently_active: currentlyActive,
      available_now: parseInt(agentStats.available_now) || 0,
      on_session: parseInt(activeAgents.on_session) || 0,
      utilization_rate: totalAssigned > 0 ? 
        Math.round((parseInt(activeAgents.on_session) / totalAssigned) * 100) : 0,
      agent_list: agentList.map(agent => ({
        id: agent.id,
        name: agent.name,
        email: agent.email,
        disponibilidad: agent.disponibilidad,
        sessions_today: parseInt(agent.sessions_today) || 0,
        is_primary: agent.is_primary_agent,
        max_concurrent: agent.max_concurrent_chats
      }))
    };

  } catch (error) {
    Logger.error('Error obteniendo métricas de agentes de sala', error);
    return {};
  }
}

/**
 * Métricas de mensajes para una sala específica
 */
async function getRoomMessageMetrics(roomId, targetDate) {
  try {
    const [metrics] = await sequelize.query(`
      SELECT 
        COUNT(m.id) as total_messages,
        COUNT(CASE WHEN m.sender_type = 'patient' THEN 1 END) as patient_messages,
        COUNT(CASE WHEN m.sender_type IN ('agent', 'supervisor') THEN 1 END) as agent_messages,
        COUNT(DISTINCT m.session_id) as sessions_with_messages
      FROM chat_messages m
      JOIN chat_sessions s ON m.session_id = s.id
      WHERE (s.room_uuid::text = $1 OR s.room_id = $1)
      AND DATE(m.created_at) = $2
    `, {
      bind: [roomId, targetDate],
      type: sequelize.QueryTypes.SELECT
    });

    const totalMessages = parseInt(metrics.total_messages) || 0;
    const sessionsWithMessages = parseInt(metrics.sessions_with_messages) || 0;
    const patientMessages = parseInt(metrics.patient_messages) || 0;
    const agentMessages = parseInt(metrics.agent_messages) || 0;

    return {
      total_messages: totalMessages,
      patient_messages: patientMessages,
      agent_messages: agentMessages,
      avg_per_session: sessionsWithMessages > 0 ? 
        Math.round((totalMessages / sessionsWithMessages) * 10) / 10 : 0,
      response_ratio: patientMessages > 0 ? 
        Math.round((agentMessages / patientMessages) * 100) / 100 : 0
    };

  } catch (error) {
    Logger.error('Error obteniendo métricas de mensajes de sala', error);
    return {};
  }
}

/**
 * Obtener tendencias de la sala (últimos N días)
 */
async function getRoomTrends(roomId, days = 7) {
  try {
    const trends = await sequelize.query(`
      SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned
      FROM chat_sessions
      WHERE (room_uuid::text = $1 OR room_id = $1)
      AND created_at >= CURRENT_DATE - INTERVAL '${days} days'
      GROUP BY DATE(created_at)
      ORDER BY date ASC
    `, {
      bind: [roomId],
      type: sequelize.QueryTypes.SELECT
    });

    return trends.map(t => ({
      date: t.date,
      sessions: parseInt(t.total_sessions) || 0,
      completed: parseInt(t.completed) || 0,
      abandoned: parseInt(t.abandoned) || 0
    }));

  } catch (error) {
    Logger.error('Error obteniendo tendencias de sala', error);
    return [];
  }
}

/**
 * Calcular score de satisfacción basado en tiempo de respuesta
 */
function calculateRoomSatisfaction(avgResponseTime) {
  if (avgResponseTime === 0) return 0;
  
  // Escala: 3 min o menos = 100, cada minuto adicional resta 10 puntos
  const score = Math.max(0, Math.min(100, 100 - ((avgResponseTime - 3) * 10)));
  return Math.round(score);
}

/**
 * Métricas de rendimiento para una sala específica
 */
async function getRoomPerformanceMetrics(roomId, targetDate, timeframe) {
  try {
    // Tiempo de respuesta promedio
    const [responseTime] = await sequelize.query(`
      WITH first_messages AS (
        SELECT 
          m.session_id,
          MIN(CASE WHEN m.sender_type = 'patient' THEN m.created_at END) as first_patient_msg,
          MIN(CASE WHEN m.sender_type IN ('agent', 'supervisor') THEN m.created_at END) as first_agent_msg
        FROM chat_messages m
        JOIN chat_sessions s ON m.session_id = s.id
        WHERE (s.room_uuid::text = $1 OR s.room_id = $1)
        AND DATE(m.created_at) = $2
        GROUP BY m.session_id
      )
      SELECT 
        AVG(
          CASE 
            WHEN first_patient_msg IS NOT NULL AND first_agent_msg IS NOT NULL
                 AND first_agent_msg > first_patient_msg
            THEN EXTRACT(EPOCH FROM (first_agent_msg - first_patient_msg))/60
            ELSE NULL
          END
        ) as avg_response_time,
        COUNT(CASE 
          WHEN first_patient_msg IS NOT NULL AND first_agent_msg IS NOT NULL
               AND first_agent_msg > first_patient_msg
               AND EXTRACT(EPOCH FROM (first_agent_msg - first_patient_msg))/60 <= 3
          THEN 1 
        END) as within_goal,
        COUNT(*) as total_sessions
      FROM first_messages
    `, {
      bind: [roomId, targetDate],
      type: sequelize.QueryTypes.SELECT
    });

    const avgResponseTime = parseFloat(responseTime.avg_response_time) || 0;
    const withinGoal = parseInt(responseTime.within_goal) || 0;
    const totalSessions = parseInt(responseTime.total_sessions) || 0;

    // Tendencias (últimos 7 días)
    const trends = await getRoomTrends(roomId, 7);

    return {
      avg_response_time: Math.round(avgResponseTime * 100) / 100,
      avg_wait_time: 0,
      goal_achievement_rate: totalSessions > 0 ? 
        Math.round((withinGoal / totalSessions) * 100) : 0,
      satisfaction_score: calculateRoomSatisfaction(avgResponseTime),
      trends
    };

  } catch (error) {
    Logger.error('Error obteniendo métricas de rendimiento de sala', error);
    return {};
  }
}

/**
 * Generar alertas para la sala
 */
function generateRoomAlerts(sessionMetrics, agentMetrics, performanceMetrics) {
  const alerts = [];

  // Alerta de sesiones en espera
  if (sessionMetrics.waiting_sessions > 5) {
    alerts.push({
      type: 'warning',
      priority: 'high',
      message: `${sessionMetrics.waiting_sessions} sesiones en espera`,
      action: 'Considere asignar más agentes a esta sala'
    });
  }

  // Alerta de tasa de abandono alta
  if (sessionMetrics.abandonment_rate > 20) {
    alerts.push({
      type: 'error',
      priority: 'urgent',
      message: `Tasa de abandono alta: ${sessionMetrics.abandonment_rate}%`,
      action: 'Revisar tiempos de respuesta y disponibilidad de agentes'
    });
  }

  // Alerta de pocos agentes disponibles
  if (agentMetrics.available_now < 2) {
    alerts.push({
      type: 'warning',
      priority: 'medium',
      message: `Solo ${agentMetrics.available_now} agente(s) disponible(s)`,
      action: 'Verificar horarios y disponibilidad de agentes'
    });
  }

  // Alerta de tiempo de respuesta lento
  if (performanceMetrics.avg_response_time > 5) {
    alerts.push({
      type: 'warning',
      priority: 'medium',
      message: `Tiempo de respuesta promedio: ${performanceMetrics.avg_response_time.toFixed(1)} min`,
      action: 'Tiempo de respuesta por encima del objetivo (3 min)'
    });
  }

  return alerts;
}

// ==================== CONTROLADORES ====================

/**
 * GET /supervisor/rooms/:roomId/dashboard
 * Dashboard completo de métricas para una sala específica
 */
async function getRoomDashboard(req, res) {
  try {
    const { roomId } = req.params;
    const { date, timeframe = '24h' } = req.query;
    const supervisorId = req.user?.id;

    Logger.info('🎯 Supervisor solicitando dashboard de sala', {
      supervisor_id: supervisorId,
      room_id: roomId,
      date,
      timeframe
    });

    // 1️⃣ VERIFICAR QUE LA SALA EXISTE
    const [room] = await sequelize.query(`
      SELECT id, name, description, room_type, is_active, status
      FROM chat_rooms
      WHERE id = $1 AND deleted_at IS NULL
    `, {
      bind: [roomId],
      type: sequelize.QueryTypes.SELECT
    });

    if (!room) {
      return ResponseHelper.notFound(res, 'Sala');
    }

    // 2️⃣ VERIFICAR QUE EL SUPERVISOR TIENE ACCESO A ESTA SALA
    const hasAccess = await verifySupervisorRoomAccess(supervisorId, roomId);
    
    if (!hasAccess) {
      return ResponseHelper.error(res, 
        'No tienes acceso a esta sala', 
        403, 
        'FORBIDDEN_ROOM_ACCESS'
      );
    }

    // 3️⃣ OBTENER MÉTRICAS DE LA SALA
    const targetDate = date || new Date().toISOString().split('T')[0];
    
    const [sessionMetrics, agentMetrics, messageMetrics, performanceMetrics] = await Promise.all([
      getRoomSessionMetrics(roomId, targetDate),
      getRoomAgentMetrics(roomId, targetDate),
      getRoomMessageMetrics(roomId, targetDate),
      getRoomPerformanceMetrics(roomId, targetDate, timeframe)
    ]);

    // 4️⃣ CONSTRUIR RESPUESTA
    const dashboard = {
      room_info: {
        id: room.id,
        name: room.name,
        description: room.description,
        room_type: room.room_type,
        is_active: room.is_active,
        status: room.status
      },
      
      date: targetDate,
      timeframe,
      
      // Métricas principales
      metrics: {
        sessions: {
          total: sessionMetrics.total_sessions || 0,
          active: sessionMetrics.active_sessions || 0,
          waiting: sessionMetrics.waiting_sessions || 0,
          completed: sessionMetrics.completed_sessions || 0,
          abandoned: sessionMetrics.abandoned_sessions || 0,
          avg_duration: sessionMetrics.avg_duration || 0,
          attendance_rate: sessionMetrics.attendance_rate || 0,
          abandonment_rate: sessionMetrics.abandonment_rate || 0
        },
        
        agents: {
          total_assigned: agentMetrics.total_assigned || 0,
          currently_active: agentMetrics.currently_active || 0,
          available_now: agentMetrics.available_now || 0,
          on_session: agentMetrics.on_session || 0,
          utilization_rate: agentMetrics.utilization_rate || 0
        },
        
        messages: {
          total_today: messageMetrics.total_messages || 0,
          from_patients: messageMetrics.patient_messages || 0,
          from_agents: messageMetrics.agent_messages || 0,
          avg_per_session: messageMetrics.avg_per_session || 0,
          response_ratio: messageMetrics.response_ratio || 0
        },
        
        performance: {
          avg_response_time: performanceMetrics.avg_response_time || 0,
          avg_wait_time: performanceMetrics.avg_wait_time || 0,
          goal_achievement_rate: performanceMetrics.goal_achievement_rate || 0,
          satisfaction_score: performanceMetrics.satisfaction_score || 0
        }
      },
      
      // Datos para gráficos
      trends: performanceMetrics.trends || [],
      
      // Agentes activos en esta sala
      active_agents: agentMetrics.agent_list || [],
      
      // Alertas y notificaciones
      alerts: generateRoomAlerts(sessionMetrics, agentMetrics, performanceMetrics),
      
      // Metadata
      generated_at: new Date().toISOString(),
      generated_by: req.user?.name || 'Supervisor',
      data_source: 'real_database_tables',
      tables_used: ['chat_sessions', 'chat_messages', 'agent_assignments', 'user']
    };

    return ResponseHelper.success(res, 'Dashboard de sala obtenido', dashboard);

  } catch (error) {
    Logger.error('❌ Error obteniendo dashboard de sala', error, {
      supervisor_id: req.user?.id,
      room_id: req.params.roomId
    });
    return ResponseHelper.serverError(res, 'Error obteniendo dashboard de sala');
  }
}

/**
 * GET /supervisor/rooms/:roomId/statistics
 * Obtener estadísticas resumidas de una sala
 */
async function getRoomStatistics(req, res) {
  try {
    const { roomId } = req.params;
    const { timeframe = '24h' } = req.query;
    const supervisorId = req.user?.id;

    Logger.info('📊 Supervisor solicitando estadísticas de sala', {
      supervisor_id: supervisorId,
      room_id: roomId,
      timeframe
    });

    // Verificar acceso
    const hasAccess = await verifySupervisorRoomAccess(supervisorId, roomId);
    if (!hasAccess) {
      return ResponseHelper.error(res, 'No tienes acceso a esta sala', 403);
    }

    // Verificar que la sala existe
    const [room] = await sequelize.query(`
      SELECT id, name, description, room_type, is_active, status
      FROM chat_rooms
      WHERE id = $1 AND deleted_at IS NULL
    `, {
      bind: [roomId],
      type: sequelize.QueryTypes.SELECT
    });

    if (!room) {
      return ResponseHelper.notFound(res, 'Sala');
    }

    const targetDate = new Date().toISOString().split('T')[0];

    // Obtener métricas resumidas
    const [sessionMetrics, agentMetrics, messageMetrics] = await Promise.all([
      getRoomSessionMetrics(roomId, targetDate),
      getRoomAgentMetrics(roomId, targetDate),
      getRoomMessageMetrics(roomId, targetDate)
    ]);

    const statistics = {
      room_id: roomId,
      room_name: room.name,
      timeframe,
      timestamp: new Date().toISOString(),
      
      sessions: {
        total: sessionMetrics.total_sessions || 0,
        active: sessionMetrics.active_sessions || 0,
        waiting: sessionMetrics.waiting_sessions || 0,
        completed: sessionMetrics.completed_sessions || 0,
        abandoned: sessionMetrics.abandoned_sessions || 0,
        attendance_rate: sessionMetrics.attendance_rate || 0,
        abandonment_rate: sessionMetrics.abandonment_rate || 0,
        avg_duration: sessionMetrics.avg_duration || 0
      },
      
      agents: {
        total_assigned: agentMetrics.total_assigned || 0,
        currently_active: agentMetrics.currently_active || 0,
        available_now: agentMetrics.available_now || 0,
        on_session: agentMetrics.on_session || 0,
        utilization_rate: agentMetrics.utilization_rate || 0
      },
      
      messages: {
        total_today: messageMetrics.total_messages || 0,
        from_patients: messageMetrics.patient_messages || 0,
        from_agents: messageMetrics.agent_messages || 0,
        avg_per_session: messageMetrics.avg_per_session || 0
      }
    };

    return ResponseHelper.success(res, 'Estadísticas obtenidas', statistics);

  } catch (error) {
    Logger.error('❌ Error obteniendo estadísticas de sala', error);
    return ResponseHelper.serverError(res, 'Error obteniendo estadísticas');
  }
}

/**
 * GET /supervisor/rooms/:roomId/sessions
 * Obtener sesiones activas y recientes de una sala
 */
async function getRoomSessions(req, res) {
  try {
    const { roomId } = req.params;
    const { status = 'all', limit = 50 } = req.query;

    const supervisorId = req.user?.id;

    // Verificar acceso
    const hasAccess = await verifySupervisorRoomAccess(supervisorId, roomId);
    if (!hasAccess) {
      return ResponseHelper.error(res, 'No tienes acceso a esta sala', 403);
    }

    let statusFilter = '';
    if (status !== 'all') {
      statusFilter = `AND s.status = '${status}'`;
    }

    const sessions = await sequelize.query(`
      SELECT 
        s.id,
        s.status,
        s.created_at,
        s.started_at,
        s.ended_at,
        s.user_id,
        s.agent_id,
        u_patient.name as patient_name,
        u_agent.name as agent_name,
        COUNT(m.id) as message_count,
        EXTRACT(EPOCH FROM (COALESCE(s.ended_at, NOW()) - s.created_at))/60 as duration_minutes
      FROM chat_sessions s
      LEFT JOIN "user" u_patient ON s.user_id = u_patient.id
      LEFT JOIN "user" u_agent ON s.agent_id = u_agent.id
      LEFT JOIN chat_messages m ON s.id = m.session_id
      WHERE (s.room_uuid::text = $1 OR s.room_id = $1)
      ${statusFilter}
      GROUP BY s.id, s.status, s.created_at, s.started_at, s.ended_at, 
               s.user_id, s.agent_id, u_patient.name, u_agent.name
      ORDER BY s.created_at DESC
      LIMIT $2
    `, {
      bind: [roomId, parseInt(limit)],
      type: sequelize.QueryTypes.SELECT
    });

    return ResponseHelper.success(res, 'Sesiones de sala obtenidas', {
      room_id: roomId,
      sessions: sessions.map(s => ({
        id: s.id,
        status: s.status,
        patient_name: s.patient_name || 'Usuario',
        agent_name: s.agent_name || 'Sin asignar',
        created_at: s.created_at,
        duration_minutes: Math.round(parseFloat(s.duration_minutes) || 0),
        message_count: parseInt(s.message_count) || 0
      })),
      total: sessions.length
    });

  } catch (error) {
    Logger.error('Error obteniendo sesiones de sala', error);
    return ResponseHelper.serverError(res, 'Error obteniendo sesiones');
  }
}

module.exports = {
  getRoomDashboard,
  getRoomStatistics,
  getRoomSessions
};
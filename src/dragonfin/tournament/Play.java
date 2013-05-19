package dragonfin.tournament;

import java.sql.*;
import java.util.*;
import static dragonfin.tournament.Tournament.quoteSchemaName;

public class Play
{
	Tournament master;
	int playId;

	Play(Tournament master, int playId)
	{
		this.master = master;
		this.playId = playId;
	}

	Connection db()
	{
		return master.dbConn;
	}

	public ResultSetModel getParticipantsModel()
	{
		try
		{
			PreparedStatement stmt = db().prepareStatement(
				"SELECT id,player,pl.name AS player_name,"
				+" seat,handicap,turn_order,score,pp.rank AS rank"
				+" FROM playparticipant pp"
				+" LEFT JOIN player pl ON pl.id=pp.player"
				+" WHERE play=?"
				+" ORDER BY seat,player_name"
				);
			stmt.setInt(1, playId);
			ResultSet rs = stmt.executeQuery();
			ResultSetModel m = new ResultSetModel(rs);
			m.updateHandler = new MyParticipantUpdater();
			m.appendHandler = new MyParticipantAppender();
			m.hiddenColumn[0] = true;
			m.hiddenColumn[1] = true;
			m.lookupColumn[2] = new MyPlayerLookup();
			return m;
		}
		catch (SQLException e) {
			throw new RuntimeException("SQL exception: "+e,e);
		}
	}

	class MyParticipantUpdater implements ResultSetModel.UpdateHandler
	{
		public void update(Object key, String attrName, Object newValue)
			throws SQLException
		{
			PreparedStatement stmt = db().prepareStatement(
				"UPDATE playparticipant SET "
				+quoteSchemaName(attrName)+"=?"
				+" WHERE id=?"
				);
			stmt.setObject(1, newValue);
			stmt.setObject(2, key);
			stmt.executeUpdate();
		}
	}

	class MyParticipantAppender implements ResultSetModel.Appender
	{
		public void newRow()
			throws SQLException
		{
			PreparedStatement stmt = db().prepareStatement(
				"INSERT INTO playparticipant (play) VALUES (?)"
				);
			stmt.setInt(1, playId);
			stmt.executeUpdate();
		}
	}

	class MyPlayerLookup implements ResultSetModel.Lookup
	{
		//implements Lookup
		public List<LookupItem> getLookupList()
		{
			try {

			PreparedStatement stmt = db().prepareStatement(
				"SELECT id,name FROM player"
				+" WHERE id NOT IN (SELECT player FROM playparticipant WHERE play=?)"
				+" ORDER BY name,id"
				);
			stmt.setInt(1, playId);
			ResultSet rs = stmt.executeQuery();

			ArrayList<LookupItem> rv = new ArrayList<LookupItem>();
			while (rs.next()) {
				rv.add(new LookupItem(
					rs.getObject(1),
					rs.getString(2)
					));
			}
			return rv;

			}
			catch (SQLException e) {
				throw new RuntimeException("SQL exception: "+e, e);
			}
		}

		//implements Lookup
		public boolean showUnlistedOption()
		{
			return true;
		}
	}
}

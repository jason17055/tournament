package dragonfin.tournament;

import java.io.*;
import java.util.*;
import com.fasterxml.jackson.core.*;
import java.sql.*;

public class Tournament
{
	String eventName;
	String eventLocation;
	String eventBeginDate;
	String eventBeginTime;
	String eventEndDate;
	String eventEndTime;
	List<Player> players = new ArrayList<Player>();
	String[] playerCustomFields = new String[0];

	transient boolean dirty;
	transient Connection dbConn;

	public boolean isDirty()
	{
		return dirty;
	}

	public void loadFile(File file)
		throws IOException
	{
		FileInputStream stream = new FileInputStream(file);
		JsonParser in = new JsonFactory().createJsonParser(stream);
		parse(in);
		in.close();
	}

	public void saveFile(File file)
		throws IOException
	{
		FileOutputStream stream = new FileOutputStream(file);
		JsonGenerator out = new JsonFactory().createJsonGenerator(stream);
		write(out);
		out.close();
	}

	public void parse(JsonParser in)
		throws IOException
	{
		if (in.nextToken() != JsonToken.START_OBJECT)
			throw new JsonParseException("expected START_OBJECT",
				in.getCurrentLocation());
		while (in.nextToken() != JsonToken.END_OBJECT)
		{
			String fieldName = in.getCurrentName();
			in.nextToken();
			if (fieldName.equals("eventName")) {
				eventName = in.getText();
			}
			else if (fieldName.equals("eventLocation")) {
				eventLocation = in.getText();
			}
			else if (fieldName.equals("eventBeginDate")) {
				eventBeginDate = in.getText();
			}
			else if (fieldName.equals("eventBeginTime")) {
				eventBeginTime = in.getText();
			}
			else if (fieldName.equals("eventEndDate")) {
				eventEndDate = in.getText();
			}
			else if (fieldName.equals("eventEndTime")) {
				eventEndTime = in.getText();
			}
			else if (fieldName.equals("players")) {
				parsePlayers(in);
			}
			else if (fieldName.equals("playerCustomFields")) {
				parsePlayerCustomFields(in);
			}
			else {
				in.skipChildren();
			}
		}
	}

	private void parsePlayers(JsonParser in)
		throws IOException
	{
		if (in.getCurrentToken() != JsonToken.START_ARRAY) {
			throw new JsonParseException("players: not an array",
				in.getCurrentLocation()
				);
		}

		players.clear();
		while (in.nextToken() != JsonToken.END_ARRAY) {
			Player p = new Player(this);
			p.parse(in);
			players.add(p);
		}
	}

	private void parsePlayerCustomFields(JsonParser in)
		throws IOException
	{
		if (in.getCurrentToken() != JsonToken.START_ARRAY) {
			throw new JsonParseException("playerCustomFields: not an array",
				in.getCurrentLocation()
				);
		}

		ArrayList<String> tmp = new ArrayList<String>();
		while (in.nextToken() != JsonToken.END_ARRAY) {
			String s = in.getText();
			tmp.add(s);
			in.skipChildren();
		}

		playerCustomFields = tmp.toArray(new String[0]);
	}

	public void write(JsonGenerator out)
		throws IOException
	{
		out.writeStartObject();
		out.writeStringField("eventName", eventName);
		out.writeStringField("eventLocation", eventLocation);
		out.writeStringField("eventBeginDate", eventBeginDate);
		out.writeStringField("eventBeginTime", eventBeginTime);
		out.writeStringField("eventEndDate", eventEndDate);
		out.writeStringField("eventEndTime", eventEndTime);

		if (!players.isEmpty()) {
			out.writeFieldName("players");
			out.writeStartArray();
			for (Player p : players) {
				p.write(out);
			}
			out.writeEndArray();
		}

		out.writeEndObject();
	}

	void connectDatabase()
	{
		try {

		String fileName = "db/tournamentdb";

		Class cl = Class.forName("org.hsqldb.jdbc.JDBCDriver");
		this.dbConn = DriverManager.getConnection("jdbc:hsqldb:file:"+fileName, "SA", "");

		}
		catch (Exception e) {
			throw new RuntimeException("cannot load database:"+e, e);
		}
	}

	int getSchemaVersion()
	{
		try {

			Statement stmt = dbConn.createStatement();
			stmt.execute(
			"SELECT version FROM master"
			);
			ResultSet rs = stmt.getResultSet();
			rs.next();
			return rs.getInt(1);

		}
		catch (SQLException e) {
			e.printStackTrace();
			return 0;
		}

	}

	void upgradeSchema()
	{
		int schemaVersion = getSchemaVersion();

		try {

		if (schemaVersion < 1)
		{
			Statement stmt = dbConn.createStatement();
			stmt.execute(
			"CREATE TABLE master ("
			+" version INTEGER NOT NULL,"
			+" eventName VARCHAR(200),"
			+" eventLocation VARCHAR(200),"
			+" eventStartTime TIMESTAMP WITH TIME ZONE"
			+" )"
			);
			stmt.execute(
			"INSERT INTO master (version)"
			+" VALUES (1)"
			);

			dbConn.commit();
		}

		}
		catch (SQLException e) {
			System.err.println(e);
		}
	}
}
